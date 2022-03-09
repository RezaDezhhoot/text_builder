<?php


namespace Ermac\TextBuilder;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;


class TextBuilder
{
    private $sign;

    public function __construct()
    {
        $this->sign = config('textBuilder.sign');
    }

    private function getModels($path, $namespace){
        $out = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $path
            ), RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            /**
             * @var SplFileInfo $item
             */
            if($item->isReadable() && $item->isFile() && mb_strtolower($item->getExtension()) === 'php'){
                $out[] =  $namespace .
                    str_replace("/", "\\", mb_substr($item->getRealPath(), mb_strlen($path), -4));
            }
        }
        $models = [];
        foreach ($out as $item)
            $models[] = new $item;

        return $models;
    }

    public function getParameters($modelNames = null , ...$ignores)
    {
        $models = $this->getModels(app_path("Models/"), "App\\Models\\");
        $params = [];
        $deletes = [];
        foreach ($models as $model){
            if (!is_null($modelNames) && !empty($modelNames) && (gettype($modelNames) == 'string' || gettype($modelNames) == 'object')){

                if (new $modelNames != $model) continue;
            } elseif (!is_null($modelNames) && !empty($modelNames) && gettype($modelNames) == 'array'){
                $classes = [];
                foreach ($modelNames as $modelName)
                    $classes[] = new $modelName;

                if (!in_array($model,$classes)) continue;
            }

            if (!is_null($ignores) && !empty($ignores) && (gettype($ignores) == 'string' || gettype($ignores) == 'object')){
                if (new $ignores == $model) continue;
            } elseif (!is_null($ignores) && !empty($ignores) && gettype($ignores) == 'array'){
                foreach ($ignores as $ignore)
                    if (!empty($ignore) && new $ignore == $model) continue 2;
            }

            if (method_exists($model,'getParams')){

                $localParams = $model->getParams();


                foreach ($localParams as $key => $item)
                    if (str_contains($item['param'],'^'))
                        $deletes[] = explode(',',str_replace('^','',$item['param']));

                $deletes = array_unique($this->array_values_recursive($deletes));
                foreach ($localParams as $key => $item){
                    if (str_contains($item['param'],'^'))
                        continue;

                    if ($item['param'] == '*'){
                        $columns = Schema::getColumnListing($model->getTable());
                        foreach ($columns as $column) {
                            if (!in_array($column,$deletes)){
                                $params[] = [
                                    'param' =>  $this->sign.$model->getTable().'_'.$column.$this->sign,
                                    'description' => $item['description'] ?? null
                                ];
                            }
                        }
                    } else {
                        $params[] = [
                            'param' =>  $this->sign.$model->getTable().'_'.$item['param'].$this->sign,
                            'description' => $item['description'] ?? null
                        ];
                    }
                }
            }
        }

        $counts = array_count_values($this->array_value_recursive('param',$params));
        foreach ($params as $key => $param) {
            if ($counts[$param['param']] > 1){
                if (is_null($param['description']))
                    unset($params[$key]);
            }
        }
        $globals = $this->globals();
        $params = array_merge($params,$globals);
        $params = array_values($params);
        return $params;
    }

    private function array_value_recursive($key, array $arr){
        $val = array();
        array_walk_recursive($arr, function($v, $k) use($key, &$val){
            if($k == $key) array_push($val, $v);
        });

        return $val;
    }

    public function globals()
    {
        $params = [];
        $i = 0;
        foreach (config('textBuilder.global_parameters') as $key => $param) {
            if (gettype($key) == 'integer')
                $params[$i]['param'] = $this->sign.$param.$this->sign;
            elseif (gettype($key) == 'string'){
                $params[$i]['param'] = $this->sign.$key.$this->sign;
                $params[$i]['description'] = $param;
            }
            $i++;
        }
        return $params;
    }

    private function findTextParams(string $text , array $modelParams = [] , array $ignore = [])
    {
        $params = [];
        $pattern = "/($this->sign[^$this->sign]*[^\/]$this->sign)/i";
        $text = preg_split($pattern,$text ,-1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        foreach ($text as $item)
            if ((in_array($item,$this->array_value_recursive('param',$modelParams)) && !in_array($item,$ignore)
            && !in_array($item,$this->array_value_recursive('param',$this->globals()))))
                $params[] = $item;

        return $params;
    }

    public function toParam($params = null)
    {
        $values = [];
        if (!is_null($params)) {
            if (gettype($params) == 'array'){
                foreach ($params as $item)
                    $values[] =  $this->sign.$item.$this->sign;
            } else
                $values[] =  $this->sign.$params.$this->sign;
        }
        return $values;
    }

    private function processParams(string $text , array $params , object $model)
    {
        $values = [];
        if (!empty($params) && !empty($text)){
            foreach ($params as $key => $param) {
                $_param = str_replace($this->sign,'',$param);
                $_param = str_replace($model->getTable().'_','',$_param);
                if (str_contains($_param,'-')){
                    $elements = explode('-',$_param);
                    array_push($values,$this->getRelations($elements , $model));
                } else {
                    if ((!is_null($model->{$_param}) && !empty($model->{$_param}))){
                        if (gettype($model->{$_param}) == 'object'){
                            $values[] = $this->array2string($model->{$_param}->toArray());
                        }  else $values[] = $model->{$_param};
                    }
                }
            }

        }

        return $values;
    }

    private function getRelations(array $elements , object $model , $values = [])
    {
        foreach ($elements as $key => $element) {
            unset($elements[$key]);
            if (!empty($model->{$element}) && gettype($model->{$element}) == 'object'){
                return $this->getRelations($elements,$model->{$element},$values);
                continue;
            } elseif (empty($model->{$element}) && count($model) > 0) {
                $case = [];
                foreach ($model as $item){
                    if (!empty($item->{$element}) && gettype($item->{$element}) == 'object' ||
                        empty($item->{$element}) && count($item) > 0) {
                        return $this->getRelations($elements,$item->{$element},$values);
                    } else $case[] = $item->{$element};
                }
                $values = implode(',',$case);
                continue;
            }

            if(!empty($model->{$element}) && gettype($model->{$element}) !='object' && !is_null($model->{$element})) {
                $values = $model->{$element};
                continue;
            }
        }
        return $values;
    }

    private function array2string($data){
        $log_a = "";
        foreach ($data as $key => $value) {
            if(is_array($value)) $log_a .= "[".$key."] => (". $this->array2string($value). ") \n";
            else $log_a .= "[".$key."] => ".$value."\n";
        }
        return $log_a;
    }

    public function array_values_recursive($ary)
    {
        $lst = array();
        foreach( array_keys($ary) as $k ){
            $v = $ary[$k];
            if (is_scalar($v)) {
                $lst[] = $v;
            } elseif (is_array($v)) {
                $lst = array_merge( $lst,
                    $this->array_values_recursive($v)
                );
            }
        }
        return $lst;
    }

    public function make(string $text , $model , $ignore = null)
    {
        if (gettype($model) == 'array') {
            $textParams = [];
            $values = [];
            foreach ($model as $key => $item) {
                $modelParams = $this->getParameters($item);
                $textParams[$key] = $this->findTextParams($text , $modelParams , $this->toParam($ignore));
                $values[$key] = $this->processParams($text,$textParams[$key],$item);
            }

            return str_replace($this->array_values_recursive($textParams),$this->array_values_recursive($values),$text);
        } else {
            $modelParams = $this->getParameters([$model]);

            $textParams = $this->findTextParams($text , $modelParams , $this->toParam($ignore));

            $values = $this->processParams($text,$textParams,$model);

            return str_replace($this->array_value_recursive('param',$textParams),$values,$text);

        }
    }

    public function set(string $text , $params , $values)
    {
        $param = [];
        foreach ($params as $item)
            $param[] = $this->sign.$item.$this->sign;

        return str_replace($param,$values,$text);
    }
}
