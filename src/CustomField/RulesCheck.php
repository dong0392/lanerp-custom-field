<?php

namespace lanerp\dong\CustomField;

use lanerp\common\Helpers\Strs;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Department;
use App\Models\User;
use App\Models\UserTag;

class RulesCheck
{
    /**
     * Notes:通用规则验证方法
     * Date: 2024/12/10
     * @param $rules
     * @param $checkValues
     * @return bool
     */
    public static function validate($rules, $checkValues): bool
    {
        $boolean = $rules["boolean"] ?? null;
        $rules   = $rules["rules"] ?? [];
        $pass    = ($boolean === "and" || ($boolean === null || !$rules));
        foreach ($rules as $rule) {
            $fieldType  = $rule["field_type"];
            $checkValue = ValueExtractor::$fieldType($checkValues[$rule["field_key"]] ?? []);
            $sign       = static::$fieldType($checkValue, $rule["field_value"], $rule["operator"]);
            if ($boolean === "or" && $sign) {
                $pass = true;
                break;
            }
            if ($boolean === "and" && !$sign) {
                $pass = false;
                break;
            }
        }
        return $pass;
    }

    public static function __callStatic($method, $args)
    {
        $method = toCamelCase($method);
        if (method_exists(static::class, $method)) {
            // 调用转换后的方法
            return static::$method(...$args);
        } else {
            // 如果方法不存在，则调用默认的处理方法
            return static::default(...$args);
        }

        //throw new \BadMethodCallException("Method {$method} does not exist.");
    }

    public static function default($checkValue, $ruleValue, $operator = null)
    {
        return false;
    }

    public static function radio($checkValue, $ruleValue, $operator = null)
    {
        return in_array($checkValue, $ruleValue);
    }

    public static function radioWorkorderType($checkValue, $ruleValue, $operator = null)
    {
        return in_array($checkValue, $ruleValue);
    }

    public static function checkbox($checkValue, $ruleValue, $operator = null)
    {
        return (bool)array_intersect($checkValue, $ruleValue);
    }

    public static function cascader($checkValue, $ruleValue, $operator = null)
    {
        return in_array($checkValue, $ruleValue);
    }

    public static function date($checkValue, $ruleValue, $operator = null)
    {
        return $checkValue >= $ruleValue[0] && $checkValue <= $ruleValue[1];
    }

    public static function contact($checkValue, $ruleValue, $operator = null)
    {
        return (bool)array_intersect($checkValue, $ruleValue);
    }

    public static function workorderRole($checkValue, $ruleValue, $operator = null)
    {
        return (bool)array_intersect($checkValue, $ruleValue);
    }

    public static function department($checkValue, $ruleValue, $operator = null)
    {
        return (bool)array_intersect($checkValue, $ruleValue);
    }
}
