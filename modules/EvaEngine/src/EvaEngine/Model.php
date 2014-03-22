<?php

namespace Eva\EvaEngine;

class Model extends \Phalcon\Mvc\Model
{
    protected $prefix = 'eva_';

    protected $tableName;

    protected $useMasterSlave = true;

    public function getSource() {
        return $this->prefix . $this->tableName;
    }

    public function initialize()
    {
        if(true === $this->useMasterSlave) {
            $this->setWriteConnectionService('dbMaster');
            $this->setReadConnectionService('dbSlave');
        }
    }
}
