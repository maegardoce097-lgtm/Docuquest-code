<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Welcome extends Controller
{
    public function health()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['status' => 'ok']);
    }

    public function index()
    {
        $frontend = ROOT_DIR . 'public/react/index.html';
        if (!is_file($frontend)) {
            http_response_code(200);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'DocuQuest backend is running. The frontend is hosted separately.';
            return;
        }

        header('Cache-Control: no-cache, no-store, must-revalidate');
        $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $webRoot = rtrim(str_replace('\\', '/', dirname($scriptPath)), '/');
        if (preg_match('~/react$~i', $webRoot)) {
            $webRoot = substr($webRoot, 0, -strlen('/react'));
        }
        $apiBase = ($webRoot ?: '') . '/api';
        $assetBase = ($webRoot ?: '') . '/react/';
        $html = file_get_contents($frontend);
        $html = str_replace('../api', htmlspecialchars($apiBase, ENT_QUOTES, 'UTF-8'), $html);
        $html = str_replace('<base href="./" />', '<base href="' . htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') . '" />', $html);
        echo $html;
    }
}
