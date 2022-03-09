<?php
namespace Ermac\TextBuilder;

trait HasParams
{
    public function getParams()
    {
        $params = [];
        $i = 0;
        foreach ($this->params as $key => $param) {
            if (gettype($key) == 'integer')
                $params[$i]['param'] = $param;
            elseif (gettype($key) == 'string'){
                $params[$i]['param'] = $key;
                $params[$i]['description'] = $param;
            }
            $i++;
        }
        return $params;
    }
}
