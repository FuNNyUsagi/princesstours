<?php
namespace app\extension;

use yii\db\Command;
use Yii;

class CustomMysqlCommand extends Command
{
    protected $_prevPendingParams = [];

    public function __construct($config = [])
    {
        parent::__construct($config);
    }

    public function bindValue($name, $value, $dataType = null)
    {
        $this->_prevPendingParams[$name] = [$value, $dataType];
        return parent::bindValue($name, $value, $dataType);
    }

    public function bindValues($values)
    {
        $schema = $this->db->getSchema();
        foreach ($values as $name => $value) {
            if (is_array($value)) {
                $this->_prevPendingParams[$name] = $value;
            } else {
                $type = $schema->getPdoType($value);
                $this->_prevPendingParams[$name] = [$value, $type];
            }
        }
        return parent::bindValues($values);
    }

    public function rebindValues()
    {
        foreach ($this->_prevPendingParams as $name => $data) {
            $value = $data[0];
            $dataType = !empty($data[1]) ? $data[1] : null;
            parent::bindValue($name, $value, $dataType);
        }
        return $this;
    }

    protected function queryInternal($method, $fetchMode = null)
    {
        try {
            return parent::queryInternal($method, $fetchMode);
        } catch (\yii\db\Exception $e) {
            if ($e->errorInfo) {
                if ($e->errorInfo[1] == 2006 || $e->errorInfo[1] == 2013) {
                    Yii::warning('[Reconnection database]');
                    Yii::getLogger()->flush(true);

                    $this->db->close();
                    $this->db->open();
                    $this->pdoStatement = null;
                    $this->rebindValues();
                    return parent::queryInternal($method, $fetchMode);
                }
            }
            throw $e;
        }
        $this->_prevPendingParams = [];
    }

    public function execute()
    {
        try {
            return parent::execute();
        } catch (\yii\db\Exception $e) {
            if ($e->errorInfo) {
                if ($e->errorInfo[1] == 2006 || $e->errorInfo[1] == 2013) {
                    Yii::warning('[Reconnection database]');
                    Yii::getLogger()->flush(true);

                    $this->db->close();
                    $this->db->open();
                    $this->pdoStatement = null;
                    $this->rebindValues();
                    return parent::execute();
                }
            }
            throw $e;
        }
        $this->_prevPendingParams = [];
    }
}