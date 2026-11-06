<?php

class Create_login_attempts_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if ($this->_lava->dbforge->table_exists('login_attempts')) {
            return;
        }

        $this->_lava->dbforge
            ->add_field([
                'email_hash' => [
                    'type'       => 'CHAR',
                    'constraint' => 64,
                    'null'       => FALSE,
                ],
                'attempts' => [
                    'type'       => 'TINYINT',
                    'constraint' => 3,
                    'unsigned'   => TRUE,
                    'null'       => FALSE,
                    'default'    => 0,
                ],
                'window_started_at' => [
                    'type'    => 'DATETIME',
                    'null'    => FALSE,
                    'default' => 'CURRENT_TIMESTAMP',
                ],
                'locked_until' => [
                    'type'    => 'DATETIME',
                    'null'    => TRUE,
                    'default' => NULL,
                ],
            ])
            ->add_key('email_hash', primary: TRUE)
            ->create_table('login_attempts');
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('login_attempts');
    }
}