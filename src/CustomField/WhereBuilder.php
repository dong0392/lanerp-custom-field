<?php

namespace lanerp\dong\CustomField;

use lanerp\dong\Models\CustomField;

class WhereBuilder
{
    private static WhereBuilder $instances;
    private ?string             $form;
    private ?string             $as;
    private string              $extends;


    //自定义筛选
    private string $boolean;//where逻辑
    private array  $where       = [];//所有where条件
    public array   $logicField  = [];//前端传来的所有逻辑性字段
    public array   $logicValue  = [];//前端传来的所有逻辑性字段值
    public array   $systemField = [];//前端传来的所有系统字段

    //转where条件的字段类型
    private array $systemFieldType = [
        "checkbox"             => "Checkbox",//多选
        "date_range"           => "DateRange",//日期区间
        "date_timestamp_range" => "DateTimestampRange",//时间戳区间
    ];
    private array $fieldType       = [
        "input"                     => "default",//文本 like
        "textarea"                  => "default",//多行文本 like
        "number"                    => "default",//数字 =
        "money"                     => "default",//金额 =
        "serial_number"             => "default",//流水号 like
        "compute_mode"              => "default",//计算工式 =
        "order_amount_compute_mode" => "default",//订单计算工式 =
        "rate"                      => "default",//评分 =
        "radio"                     => "default",//单选 in
        "telephone"                 => "default",//电话 like
        "id_card"                   => "default",//身份证 like
        "address"                   => "checkboxByRadio",//地址 in
        "contact"                   => "checkbox",//联系人
        "department"                => "checkbox",//部门
        "checkbox"                  => "checkbox",//多选
        "cascader"                  => "checkbox",//级联
        "date"                      => "date",//日期
        "attendance_date"           => "date",//考勤日期
        "date_range"                => "dateRange",//日期区间
        "table"                     => "table",//表格
        "checkbox_approval"         => "checkbox",//关联审批单
        "checkbox_check_record"     => "checkbox",//关联打卡记录
        "paying_teller"             => "multiText",//收付款账户
    ];


    public function __construct(?string $form, ?string $as)
    {
        $this->form    = $form;
        $this->as      = $as ? $as . "." : "";
        $this->extends = $this->as . "extends";
    }

    public static function init($form = null, $as = null)
    {
        return static::$instances ?? (static::$instances = new static($form, $as));
    }

    public function fieldExists(...$field): bool
    {
        if (count(array_intersect($field, [...$this->logicField, ...$this->systemField])) > 0) {
            return true;
        }
        return false;
    }

    /**
     * Notes:生成keyword条件where
     * Date: 2024/11/21
     * @param array $additionalFields 除表单中还要查询的字段  如：关联表中的字段
     * @param array $excludeFields    表单中不查询的系统字段 类型为："input", "textarea", "telephone", "id_card", "paying_teller", "location"
     * @return \Closure|null
     */
    public function byKeyword(array $additionalFields = [], array $excludeFields = []): ?callable
    {
        $keyword = request()->input("keyword");
        if (!$keyword) return null;
        $typeFields = CustomField::getFieldByType($this->form, ["input", "textarea", "telephone", "id_card", "paying_teller", "location"]);
        return function ($query) use ($keyword, $typeFields, $additionalFields, $excludeFields) {
            /* @var \Illuminate\Database\Eloquent\Builder $query */
            foreach ($additionalFields as $field) {
                $query->orWhere($field, "like", "%{$keyword}%");
            }
            /* @var CustomField $field */
            foreach ($typeFields as $field) {
                if ($field->is_system === CustomField::SYSTEM && !in_array($field->field_key, $excludeFields, true)) {
                    $query->orWhere("{$this->as}{$field->field_key}", "like", "%{$keyword}%");
                } else {
                    $query->orWhere("{$this->as}extends->{$field->field_key}->value", "like", "%{$keyword}%");
                }
            }
        };
    }

    //追加where条件
    public function pushWhere($where): WhereBuilder
    {
        $this->where[] = $where;
        return $this;
    }

    //自定义筛选where获取
    public function get(): ?callable
    {
        $where = null;
        if ($this->boolean && $this->where) {
            $where = function ($query) {
                /* @var \Illuminate\Database\Eloquent\Builder $query */
                foreach ($this->where as $where) {
                    if ($this->boolean === "or") {
                        $query->orWhere(...$where);
                    } else {
                        $query->where(...$where);
                    }
                }
            };
        }
        return $where;
    }

    //判断自定义筛选值是否有效
    public function isValueValid($value): bool
    {
        $valid = true;
        if (is_array($value) && !$value) {
            $valid = false;
        } elseif (is_string($value) && !$value && $value !== '0') {
            $valid = false;
        } elseif ($value === null) {
            $valid = false;
        }

        return $valid;
    }

    /*"custom_filter": [
            {
            "field_type": "input",
            "field_key": "field_2502",
            "field_value": "这一一个文本"
            },
            {
            "field_type": "paying_teller",
            "field_key": [
                "field_2524",
                "bank_name"
            ],
            "field_value": "中国银行"
            }
        ]*/
    public function byCustomFilter($customFilter = null): WhereBuilder
    {

        $customFilter = $customFilter ?? request()->input("custom_filter");

        //dd($customFilter);

        $this->boolean = $customFilter["boolean"] ?? "";

        $logicFields = $customFilter["logic_field"] ?? [];
        foreach ($logicFields as $logicField) {
            if ($this->isValueValid($logicField["field_value"])) {
                $this->logicField[]                         = $logicField["field_key"];
                $this->logicValue[$logicField["field_key"]] = $logicField["field_value"];
            }
        }

        $systemFields = $customFilter["system_field"] ?? [];
        foreach ($systemFields as $systemField) {
            if ($this->isValueValid($systemField["field_value"])) {
                $this->systemField[] = $systemField["field_key"];
                $this->where[]       = $this->bySystem($systemField);
            }
        }

        $customFields = $customFilter["custom_field"] ?? [];
        foreach ($customFields as $customField) {
            if ($this->isValueValid($customField["field_value"])) {
                $this->where[] = $this->byCustom($customField);
            }
        }
        return $this;
    }

    //系统字段where处理
    public function bySystem(array $data)
    {
        $type = $this->systemFieldType[$data["field_type"]] ?? "Default";
        return $this->{"system" . $type}($data["field_key"], $data["field_value"], $data["operator"]);
    }

    //通用字段类型
    public function systemDefault($fieldKey, $fieldValue, $operator)
    {
        $toOperator = ["not like" => "like", "not between" => "between", "not in" => "in"];
        $toOperator = $toOperator[$operator] ?? $operator;
        return match ($toOperator) {
            'like' => [$fieldKey, $operator, "%{$fieldValue}%"],
            'between' => [fn($query) => $query->whereBetween($fieldKey, $fieldValue, "and", $operator === "not between")],
            //系统字段为单选 (前端传值是多选-数组)
            "in" => [fn($query) => $query->whereIn($fieldKey, $fieldValue, "and", $operator === "not in")],
            default => [$fieldKey, $operator, $fieldValue],
        };
    }

    //系统字段为多选 (前端传值数组)
    public function systemCheckbox($fieldKey, $fieldValue, $operator)
    {
        return [
            function ($query) use ($fieldKey, $fieldValue, $operator) {
                $boolean = $operator === "in" ? "or" : "and";
                $not     = $operator === "in" ? "" : "NOT ";
                foreach ($fieldValue as $v) {
                    $query->whereRaw("{$not}FIND_IN_SET(?,{$fieldKey})", [$v], $boolean);
                }
            }
        ];
    }

    //系统字段为普通日期
    public function systemDateRange($fieldKey, $fieldValue, $operator)
    {
        $fieldValue = [$fieldValue[0], $fieldValue[1] . " 23:59:59"];
        return [fn($query) => $query->whereBetween($fieldKey, $fieldValue, "and", $operator === "not between")];
    }

    //系统字段为时间戳日期
    public function systemDateTimestampRange($fieldKey, $fieldValue, $operator)
    {
        $fieldValue = [strtotime($fieldValue[0]), strtotime($fieldValue[1]) + 86400];
        return [fn($query) => $query->whereBetween($fieldKey, $fieldValue, "and", $operator === "not between")];
    }


    /*$data={
    "field_type": "input",
    "field_key": "field_2502",
    "field_value": "这一一个文本"
    }*/
    public function byCustom(array $data)
    {
        //该自定义字段了，测试调试
        //is_string($data['field_value']) && $data['field_value'] = "'{$data['field_value']}'";
        $type = $this->fieldType[$data["field_type"]] ?? "default";
        return $this->$type($data["field_key"], $data["field_value"], $data["operator"]);
    }

    //自定义字段默认查询
    public function default($fieldKey, $fieldValue, $operator): array
    {
        if (in_array($operator, ["like", "not like"])) {
            $where = ["{$this->extends}->{$fieldKey}->value", $operator, "%{$fieldValue}%"];
        } elseif (in_array($operator, ["between", "not between"])) {
            $where = [fn($query) => $query->whereBetween("{$this->extends}->{$fieldKey}->value", $fieldValue, "and", $operator === "not between")];
        } elseif (in_array($operator, ["in", "not in"])) {
            $where = [fn($query) => $query->whereIn("{$this->extends}->{$fieldKey}->value", $fieldValue, "and", $operator === "not in")];
        } else {
            $where = ["{$this->extends}->{$fieldKey}->value", $operator, $fieldValue];
        }
        return $where;
    }

    //存值为复选,查询为单选
    public function checkboxByRadio($fieldKey, $fieldValue, $operator)
    {
        return [fn($query) => $query->whereJsonContains("{$this->extends}->{$fieldKey}->value", $fieldValue, "and", $operator !== "in")];
    }

    //存值为复选,查询也为复选
    public function checkbox($fieldKey, $fieldValue, $operator)
    {
        return [
            function ($query) use ($fieldKey, $fieldValue, $operator) {
                $boolean = $operator === "in" ? "or" : "and";
                $not     = $operator !== "in";
                foreach ($fieldValue as $v) {
                    $query->whereJsonContains("{$this->extends}->{$fieldKey}->value", $v, $boolean, $not);
                }
            }
        ];

    }

    public function date($fieldKey, $fieldValue, $operator)
    {
        $fieldValue = [$fieldValue[0], $fieldValue[1] . " 23:59:59"];
        return [fn($query) => $query->whereBetween("{$this->extends}->{$fieldKey}->value", $fieldValue, "and", $operator === "not between")];
    }

    public function dateRange($fieldKey, $fieldValue, $operator)
    {
        if ($operator === "not between") {
            //查询区间范围不在筛选区间范围内
            //满足:筛选的最大值<最小值或筛选的最小值>最大值
            $where = fn($query) => $query
                ->where("{$this->extends}->{$fieldKey}->value[0]", ">", $fieldValue[1])
                ->where("{$this->extends}->{$fieldKey}->value[1]", "<", $fieldValue[0], "or");
        } else {
            //查询区间范围在筛选区间范围内
            //满足:筛选的最大值>最小值且筛选的最小值<最大值
            $where = fn($query) => $query
                ->where("{$this->extends}->{$fieldKey}->value[0]", "<=", $fieldValue[1])
                ->where("{$this->extends}->{$fieldKey}->value[1]", ">=", $fieldValue[0]);
        }
        return [$where];
    }

    public function table($fieldKey, $fieldValue, $operator)
    {
        $not = $operator === "<>" ? "" : "not";
        return [
            fn($query) => $query
                ->whereRaw("json_unquote(json_search({$this->extends}, 'one', '{$fieldValue}', null, '$.{$fieldKey[0]}[*].{$fieldKey[1]}')) is {$not} null")
        ];
    }

    public function relateText($fieldKey, $fieldValue, $operator)
    {
        //$operator    = "like";
        //$fieldKey[1] = "approval_title";
        return ["{$this->extends}->{$fieldKey[0]}[*]->{$fieldKey[1]}", $operator, "%{$fieldValue}%"];
    }

    public function multiText($fieldKey, $fieldValue, $operator)
    {
        return ["{$this->extends}->{$fieldKey[0]}->{$fieldKey[1]}", $operator, "%{$fieldValue}%"];
    }

}
