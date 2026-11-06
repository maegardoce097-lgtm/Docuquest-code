<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Document_storage
{
    private $driver;
    private $directory;
    private $prefix;
    private $bucket;
    private $client;

    public function __construct()
    {
        $this->driver = strtolower(trim((string) (config_item('document_storage_driver') ?: 'local')));
        $this->directory = ROOT_DIR . 'runtime/uploads/request-files/';
        $prefix = trim((string) config_item('document_storage_prefix'), '/');
        $this->prefix = $prefix === '' ? '' : $prefix . '/';

        if (!in_array($this->driver, ['local', 's3'], true)) {
            throw new InvalidArgumentException('Unsupported document storage driver.');
        }
        if ($this->driver !== 's3') {
            return;
        }

        $this->bucket = trim((string) config_item('document_storage_bucket'));
        if ($this->bucket === '' || !class_exists('Aws\\S3\\S3Client')) {
            throw new RuntimeException('S3 document storage is not configured.');
        }

        $endpoint = trim((string) config_item('document_storage_endpoint'));
        $config = [
            'version' => 'latest',
            'region' => trim((string) (config_item('document_storage_region') ?: 'us-east-1')),
        ];
        $accessKey = trim((string) config_item('aws_access_key_id'));
        $secretKey = trim((string) config_item('aws_secret_access_key'));
        if ($accessKey !== '' && $secretKey !== '') {
            $config['credentials'] = [
                'key' => $accessKey,
                'secret' => $secretKey,
            ];
            $sessionToken = trim((string) config_item('aws_session_token'));
            if ($sessionToken !== '') {
                $config['credentials']['token'] = $sessionToken;
            }
        }
        if ($endpoint !== '') {
            $config['endpoint'] = $endpoint;
            $pathStyle = trim((string) config_item('document_storage_path_style'));
            $config['use_path_style_endpoint'] = $pathStyle === ''
                ? true
                : filter_var($pathStyle, FILTER_VALIDATE_BOOLEAN);
        }

        $this->client = new Aws\S3\S3Client($config);
    }

    public function put($sourcePath, $storedName, $mimeType)
    {
        if (!$this->isValidName($storedName) || !is_file($sourcePath)) {
            return false;
        }

        if ($this->driver === 's3') {
            try {
                $this->client->putObject([
                    'Bucket' => $this->bucket,
                    'Key' => $this->prefix . $storedName,
                    'SourceFile' => $sourcePath,
                    'ContentType' => $mimeType,
                ]);
                return true;
            } catch (Throwable $exception) {
                return false;
            }
        }

        if (!is_dir($this->directory)
            && !mkdir($this->directory, 0750, true)
            && !is_dir($this->directory)) {
            return false;
        }

        $destination = $this->directory . $storedName;
        return move_uploaded_file($sourcePath, $destination) || copy($sourcePath, $destination);
    }

    public function get($storedName)
    {
        if (!$this->isValidName($storedName)) {
            return null;
        }

        if ($this->driver === 's3') {
            try {
                $result = $this->client->getObject([
                    'Bucket' => $this->bucket,
                    'Key' => $this->prefix . $storedName,
                ]);
                return $result['Body']->getContents();
            } catch (Aws\S3\Exception\S3Exception $exception) {
                if ($exception->getAwsErrorCode() === 'NoSuchKey'
                    || $exception->getStatusCode() === 404) {
                    return null;
                }
                throw $exception;
            }
        }

        $path = $this->directory . $storedName;
        if (!is_file($path)) {
            return null;
        }
        $contents = file_get_contents($path);
        return $contents === false ? null : $contents;
    }

    public function listImages($continuationToken = null)
    {
        $photos = [];
        $nextToken = null;

        if ($this->driver === 's3') {
            $parameters = [
                'Bucket' => $this->bucket,
                'Prefix' => $this->prefix,
                'MaxKeys' => 100,
            ];
            if (is_string($continuationToken) && $continuationToken !== '') {
                $parameters['ContinuationToken'] = $continuationToken;
            }

            $result = $this->client->listObjectsV2($parameters);
            foreach ($result['Contents'] ?? [] as $object) {
                $storedName = substr((string) ($object['Key'] ?? ''), strlen($this->prefix));
                if (!$this->isImageName($storedName)) {
                    continue;
                }

                $lastModified = $object['LastModified'] ?? null;
                $photos[] = [
                    'key' => $storedName,
                    'mime_type' => strtolower(pathinfo($storedName, PATHINFO_EXTENSION)) === 'png' ? 'image/png' : 'image/jpeg',
                    'size_bytes' => (int) ($object['Size'] ?? 0),
                    'last_modified' => $lastModified instanceof DateTimeInterface ? $lastModified->format(DATE_ATOM) : '',
                ];
            }
            $nextToken = !empty($result['IsTruncated']) ? ($result['NextContinuationToken'] ?? null) : null;
        } elseif (is_dir($this->directory)) {
            foreach (scandir($this->directory) ?: [] as $storedName) {
                $path = $this->directory . $storedName;
                if (!$this->isImageName($storedName) || !is_file($path)) {
                    continue;
                }

                $photos[] = [
                    'key' => $storedName,
                    'mime_type' => strtolower(pathinfo($storedName, PATHINFO_EXTENSION)) === 'png' ? 'image/png' : 'image/jpeg',
                    'size_bytes' => (int) filesize($path),
                    'last_modified' => date(DATE_ATOM, filemtime($path)),
                ];
            }
            usort($photos, static function ($left, $right) {
                return strcmp($right['last_modified'], $left['last_modified']);
            });
        }

        return ['photos' => $photos, 'next_token' => $nextToken];
    }

    public function delete($storedName)
    {
        if (!$this->isValidName($storedName)) {
            return false;
        }

        if ($this->driver === 's3') {
            try {
                $this->client->deleteObject([
                    'Bucket' => $this->bucket,
                    'Key' => $this->prefix . $storedName,
                ]);
                return true;
            } catch (Throwable $exception) {
                return false;
            }
        }

        $path = $this->directory . $storedName;
        return !is_file($path) || unlink($path);
    }

    public function isImageName($storedName)
    {
        return is_string($storedName)
            && preg_match('/\A[a-f0-9]{32}\.(jpg|png)\z/i', $storedName) === 1;
    }

    private function isValidName($storedName)
    {
        return is_string($storedName)
            && preg_match('/\\A[a-f0-9]{32}\\.(pdf|jpg|png)\\z/i', $storedName) === 1;
    }
}