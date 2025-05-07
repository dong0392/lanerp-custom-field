<?php

namespace lanerp\dong\CustomField;

class ValueExtractor
{

    //解析关联商机
    public function radioProject($data)
    {
        return $data["value"] ?? 0;
    }

    //解析关联客户
    public static function radioCustomer($data)
    {
        return $data["value"] ?? 0;
    }

    //客户联系人
    public static function customerContact($data, $isFirst = false)
    {
        $value = $data["value"] ?? [];
        $isFirst && $value = $value[0] ?? 0;
        return $value;
    }

    //解析联系人
    public static function contact($data, $isFirst = false)
    {
        $value = $data["value"] ?? [];
        $isFirst && $value = $value[0] ?? 0;
        return $value;
    }


    //解析工单角色
    public static function workorderRole($data, $isFirst = false)
    {
        $value = $data["value"] ?? [];
        $isFirst && $value = $value[0] ?? 0;
        return $value;
    }

    //部门
    public static function department($data, $isFirst = false)
    {
        $value = $data["value"] ?? [];
        $isFirst && $value = $value[0] ?? 0;
        return $value;
    }

    //解析国家地区
    public static function address($data, $isAll = false)
    {
        $value = $data["value"] ?? [];
        if ($isAll) {
            //$value = [$value, $data["text"] ?? ""];
        } else {
            $value = end($value);
        }
        return $value;
    }

    //解析级联
    public static function cascader($data, $isAll = false)
    {
        $value = $data["value"] ?? [];
        !$isAll && $value = end($value);
        return $value;
    }

    //解析电话号码
    public static function telephone($data, $isPrefix = false)
    {
        $value = $data["value"] ?? "";
        $isPrefix && $value = [$value, $data["prefix"] ?? ""];
        return $value;
    }

    //解析单选
    public static function radio($data)
    {
        return $data["value"] ?? null;
    }


    //解析复选
    public static function checkbox($data)
    {
        return $data["value"] ?? [];
    }


    //解析金额
    public static function money($data)
    {
        return $data["value"] ?? 0;
    }

    //解析自定义公式
    public static function computeMode($data)
    {
        return $data["value"] ?? 0;
    }

    public static function default($data)
    {
        return $data["value"] ?? "";
    }

    public static function __callStatic($method, $args)
    {
        $method = toCamelCase($method);
        if (method_exists(static::class, $method)) {
            // 调用转换后的方法
            return call_user_func_array([static::class, $method], $args);
        } else {
            // 如果方法不存在，则调用默认的处理方法
            return static::default(...$args);
        }

        //throw new \BadMethodCallException("Method {$method} does not exist.");
    }
}
