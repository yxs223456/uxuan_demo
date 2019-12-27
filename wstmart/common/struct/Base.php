<?php
/**
 * Created by PhpStorm.
 * User: yangxs
 * Date: 2018/7/23
 * Time: 9:49
 */
namespace wstmart\common\struct;

abstract class Base
{
    protected $_types = [];

    public function __construct($values = [])
    {
        $this->setValues($values);
    }

    private function setValues($values = [])
    {
        $class = new \ReflectionClass(get_called_class());
        $props = $class->getProperties();
        foreach ($props as $prop) {
            if (!$prop->isPublic()) {
                continue;
            }
            if (is_array($values) && isset($values[$prop->getName()])) {
                $this->{$prop->getName()} = $values[$prop->getName()];
            } elseif (is_object($values) && isset($values->{$prop->getName()})) {
                $this->{$prop->getName()} = $values->{$prop->getName()};
            }
            if (isset($this->_types[$prop->getName()])) {
                switch ($this->_types[$prop->getName()]) {
                    case 'int':
                    case 'integer':
                        $this->{$prop->getName()} = (int) $this->{$prop->getName()};
                        break;
                    case 'bool':
                    case 'boolean':
                        $this->{$prop->getName()} = !!$this->{$prop->getName()};
                        break;
                    case 'float':
                    case 'double':
                    case 'real':
                        $this->{$prop->getName()} = (double) $this->{$prop->getName()};
                        break;
                    case 'string':
                        $this->{$prop->getName()} = trim((string) $this->{$prop->getName()});
                        break;
                    case 'array':
                        $this->{$prop->getName()} = (array) $this->{$prop->getName()};
                        break;
                    case 'jsonToArray':
                        if (empty($this->{$prop->getName()})){
                            $this->{$prop->getName()} = [];
                        } else {
                            $this->{$prop->getName()} = json_decode($this->{$prop->getName()},true);
                        }
                        break;
                    case 'arrayToJson':
                        if (empty($this->{$prop->getName()}) || count($this->{$prop->getName()}) <= 0){
                            $this->{$prop->getName()} = '';
                        }elseif(!is_array($this->{$prop->getName()})){
                            $this->{$prop->getName()} = (string)$this->{$prop->getName()};
                        }else{
                            $this->{$prop->getName()} = json_encode($this->{$prop->getName()},JSON_UNESCAPED_UNICODE);
                        }
                        break;
                }
            }
        }
    }

    public function toArray()
    {
        $class = new \ReflectionClass(get_called_class());
        $props = $class->getProperties();
        $data = [];
        foreach ($props as $prop) {
            if (!$prop->isPublic()) {
                continue;
            }
            $data[$prop->getName()] = $this->{$prop->getName()};
        }
        return $data;
    }
}