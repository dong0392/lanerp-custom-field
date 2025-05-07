<?php

namespace lanerp\dong\CustomField;

use App\Models\CustomField;

class FieldValueMerge
{
    protected static array $fieldType = [
        //"id_card"       => "multiText",//身份证
        //"paying_teller" => "multiText",//收付款账户
        "control_group" => "table",//控件
        "table"         => "table",//表格
    ];

    //通过表名获取
    public static function tableGet($form, $values)
    {
        $fields = CustomField::initForm($form)->filterFieldTypeFormModule()->filterFieldTypeControl()->filterRules()->getFields(true);
        return static::get($fields, $values);
    }

    //直接通过字段获取
    public static function get($fields, $values)
    {
        //dd($fields, $values);
        $mergeRes = []; // 合并后的结果数组
        foreach ($fields as $field) {
            $fieldKey    = $field['field_key'];
            $extend      = $field['extend'] ?? [];
            $hiddenField = $extend["initial_display"] ?? false;
            if (isset($values[$fieldKey]) && !$hiddenField) {
                $res        = [
                    'field_key'  => $field['field_key'],
                    'field_name' => $field['field_name'],
                    'field_type' => $field['field_type'],
                    'extend'     => []
                ];
                $extendKeys = ["tag_interaction", "visible_person_range", "col_span", "show_field", "is_show_switch", "initial_display"];
                foreach ($extendKeys as $extendKey) {
                    if (isset($extend[$extendKey])) {
                        $res['extend'][$extendKey] = $extend[$extendKey];
                    }
                }
                $type       = self::$fieldType[$field["field_type"]] ?? "default";
                $resValue   = self::$type($values[$fieldKey], $field);
                $mergeRes[] = array_merge($res, $resValue);
                //$mergeRes[] = $res;
            }
        }
        return $mergeRes;
    }

    public static function default($value)
    {
        /*if (isset($value["value"], $value["text"])) {
            $value = $value["text"];
        } elseif (isset($value["value"]) && !isset($value["text"])) {
            $value = $value["value"];
        }*/
        return ['field_value' => $value];
    }

    /*public static function multiText($value)
    {
        return ['field_value' => $value];
    }*/

    public static function table($value, $field)
    {
        $extend = $field['quote_field_key'] === "" ? $field['extend'] : $field['quote_extend'];
        //表单数据
        $tableValues = $value;
        $tableTitle  = [];
        $fieldValues = [];
        if (isset($field['_child'])) {
            $filteredFields = array_filter($field['_child'], static fn($fieldItem) => !(isset($fieldItem['extend']['initial_display']) && $fieldItem['extend']['initial_display']));
            $tableTitle     = array_map(function ($field) {
                return array(
                    "field_key"  => $field["field_key"],
                    "field_name" => $field["field_name"],
                    'field_type' => $field['field_type'],
                );
            }, $filteredFields);
            //dd($tableTitle);
            foreach ($tableValues as $tableValue) {
                //$fieldValues[]   = Arrs::arrayColumn(self::fieldValueMerge(0, '', $tableValue, $field['_child']), 'field_value', 'field_key');
                $tempFieldValues = self::get($field['_child'], $tableValue);
                $tempArr         = [];
                //dd($tempFieldValues);
                foreach ($tempFieldValues as $tempv) {
                    //if(is_array($tempv["field_value"])){
                    if (isset($tempv["table_title"])) {
                        $tempArr[$tempv['field_key']] = $tempv;
                    } else {
                        $tempArr[$tempv['field_key']] = $tempv["field_value"];
                    }
                }
                $fieldValues[] = $tempArr;
            }
            //dd($tempFieldKeys);
        }
        $res['table_title']              = $tableTitle;
        $res['field_value']              = $fieldValues;
        $res['extend']['filling_method'] = $extend['filling_method'] ?? 1;
        return $res;
    }
}
