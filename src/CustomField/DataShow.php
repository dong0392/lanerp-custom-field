<?php

namespace lanerp\common\Helpers\CustomField;

use lanerp\common\Helpers\Strs;
use App\Models\CustomField;
use App\Models\CustomForm;

class DataShow
{

    private string $form;
    private string $model;
    private array  $data;
    private ?array $fields;
    private array  $queryTypeValue;
    private array  $valueByType = [
        "number"             => "Number",//数字
        "compute_mode"       => "Number",//数字
        "money"              => "Money",//金额
        "money_compute_mode" => "Money",//金额
        "contact"            => "Contact",//联系人
        "customer_contact"   => "CustomerContact",//客户联系人
        "radio_customer"     => "RadioCustomer",//客户
        "radio_project"      => "RadioProject",//商机
        "radio_order"        => "RadioOrder",//订单
        "department"         => "Department",//部门
        "address"            => "Address",//国家地区
        "telephone"          => "Telephone",//国家地区
        "radio"              => "Radio",//单选
        "checkbox"           => "Checkbox",//复选
        "paying_teller"      => "PayingTeller",//复选
    ];

    public function __construct($data, $form, $fields = null)
    {
        $this->data           = $data;
        $this->form           = $form;
        $this->fields         = $fields;
        $this->queryTypeValue = [];
    }

    public function getFields()
    {
        if ($this->fields) {
            $fields = collect($this->fields)->filter(fn($field) => $field['is_system'] === 1);
        } else {
            $fields = CustomField::getFieldBySystem($this->form);
        }
        return $fields;
    }

    public static function list($data, $form = "", $fields = null)
    {
        if (isset($data["data"])) {
            $data["data"] = (new static($data["data"], $form, $fields))->listBy();
        } else {
            $data = (new static($data, $form, $fields))->listBy();
        }
        return $data;
    }

    public static function details($data, $form = "", $fields = null)
    {
        return (new static($data, $form, $fields))->detailsBy();
    }

    public function detailsBy()
    {
        if ($this->data) {
            $fields     = $this->getFields();
            $this->data = array_merge($this->data, $this->data['extends'] ?? []);
            unset($this->data['extends']);
            $this->data          = $this->valueFormat($this->data, $fields);
            $fieldTypeValueIndex = [];
            foreach ($this->queryTypeValue as $fieldType => $value) {
                $fieldTypeValueIndex[$fieldType] = $this->{"query" . toPascalCase($fieldType) . "Value"}($value);
            }
            //需要查询的值再进行处理
            if ($fieldTypeValueIndex) {
                $this->data = $this->queryValueFormat($this->data, $fields, $fieldTypeValueIndex);
            }
        }
        return $this->data;
    }

    public function listBy()
    {
        $fields = $this->getFields();
        //$fieldByType = $this->fieldByType($fields);
        //$fieldIndex = $fields->keyBy("field_key");
        //值进行格式化处理
        foreach ($this->data as &$row) {
            $row = array_merge(...([$row, $row['extends'] ?? []]));
            unset($row['extends']);
            $row = $this->valueFormat($row, $fields);
        }
        unset($row);
        $fieldTypeValueIndex = [];
        foreach ($this->queryTypeValue as $fieldType => $value) {
            $fieldTypeValueIndex[$fieldType] = $this->{"query" . toPascalCase($fieldType) . "Value"}($value);
        }
        //需要查询的值再进行处理
        if ($fieldTypeValueIndex) {
            foreach ($this->data as &$row) {
                $row = $this->queryValueFormat($row, $fields, $fieldTypeValueIndex);
            }
        }
        return $this->data;
    }

    //第二次格式化，对查询出来的值再次格式化
    public function queryValueFormat($data, $fields, $fieldTypeValueIndex)
    {
        foreach ($fields as $field) {
            if ($field["status"] === 0 && array_key_exists($field["field_key"], $data) && !isset($data[$field["field_key"]]["value"]) && isset($fieldTypeValueIndex[$field["field_type"]]) && $field["field_type"] !== "system") {
                $value                     = $data[$field["field_key"]];
                $data[$field["field_key"]] = [
                    "value" => $value,
                    "text"  => $this->queryTextBy($value, $fieldTypeValueIndex[$field["field_type"]])
                ];
            }
        }

        return $data;
    }

    //值第一次格式化 start
    public function valueFormat($data, $fields)
    {
        foreach ($fields as $field) {
            if ($field["status"] === 0 && array_key_exists($field["field_key"], $data) && !is_array($data[$field["field_key"]]) && $field["field_type"] !== "system") {
                $data[$field["field_key"]] = $this->{"valueBy" . ($this->valueByType[$field["field_type"]] ?? "Default")}($data, $field["field_key"]);
            } elseif (in_array($field["field_type"], ["paying_teller", "date_range_apm"], true)) {
                $value = $this->{"valueBy" . (toPascalCase($field["field_type"]))}($data, $field["field_key"]);
                if ($value !== null) $data[$field["field_key"]] = $value;
            }
        }

        return $data;
    }

    public function valueByDefault($data, $fieldKey): array
    {
        return ["value" => $data[$fieldKey] ?? ""];
    }

    public function valueByNumber($data, $fieldKey): array
    {
        $value = $data[$fieldKey] ?? 0;
        return ValueCompile::number($value);
    }

    public function valueByAddress($data, $fieldKey): array
    {
        $value = [];
        array_key_exists("country", $data) && $value[] = $data["country"];
        array_key_exists("province", $data) && $value[] = $data["province"];
        array_key_exists("city", $data) && $value[] = $data["city"];
        array_key_exists("area", $data) && $value[] = $data["area"];
        return ValueCompile::address($value, $data[$fieldKey], $data["addr"] ?? null);
    }

    public function valueByPayingTeller($data, $fieldKey): array
    {
        $fieldKey    = explode("_", $fieldKey)[0];
        $value       = $data[$fieldKey . "_card_number"] ?? null;
        $accountName = $data[$fieldKey . "_account_name"] ?? "";
        $bankName    = $data[$fieldKey . "_bank_name"] ?? "";
        $bankBranch  = $data[$fieldKey . "_bank_branch"] ?? "";
        $accountType = $data[$fieldKey . "_account_type"] ?? 0;
        return ValueCompile::payingTeller($value, $accountName, $bankName, $bankBranch, $accountType);
    }

    public function valueByDateRangeApm($data, $fieldKey): array
    {
        $fieldKey  = explode("_", $fieldKey)[0];
        $startDate = $data[$fieldKey . "_start_date"] ?? null;
        $startTime = $data[$fieldKey . "_start_time"] ?? 0;
        $endDate   = $data[$fieldKey . "_end_date"] ?? null;
        $endTime   = $data[$fieldKey . "_end_time"] ?? 0;
        return ValueCompile::dateRangeApm($startDate, $endDate, $startTime, $endTime);
    }

    public function valueByMoney($data, $fieldKey): array
    {
        $value          = $data[$fieldKey];
        $text           = $data[$fieldKey] ?? null;
        $currency       = $data[$fieldKey . "_currency"] ?? $data["currency"] ?? null;
        $valueConverted = $data[$fieldKey . "_converted"] ?? null;
        return ValueCompile::money($value, $text, $currency, $valueConverted);
    }

    public function valueByTelephone($data, $fieldKey): array
    {
        $prefix = $data[$fieldKey . "_prefix"] ?? "+86";
        return ValueCompile::telephone($data[$fieldKey], $prefix);
    }

    public function valueByRadio($data, $fieldKey): array
    {
        $value = $data[$fieldKey];
        $text  = "";
        if (isset($data["{$fieldKey}_name"])) {
            $text = $data["{$fieldKey}_name"];
        } elseif ((class_exists($className = $this->getModel()) && $constantName = strtoupper($fieldKey) . "_ENUM") && defined("$className::$constantName")) {
            $text = data_get(constant("$className::$constantName"), "{$value}.title", "");
        }

        return ["value" => $value, "text" => $text];
    }

    public function valueByCheckbox($data, $fieldKey): array
    {
        //$value = is_array($data[$fieldKey]) ? $data[$fieldKey] : explode(",", $data[$fieldKey]);
        $value = Strs::explodeToInt($data[$fieldKey]);
        $text  = [];
        if (isset($data["{$fieldKey}_name"])) {
            $text = $data["{$fieldKey}_name"];
        } elseif ((class_exists($className = $this->getModel()) && $constantName = strtoupper($fieldKey) . "_ENUM") && defined("$className::$constantName")) {
            foreach ($value as $v) {
                $text[] = data_get(constant("$className::$constantName"), "{$v}.title", "");
            }
        }
        return ["value" => $value, "text" => $text];
    }

    public function valueByContact($data, $fieldKey)
    {
        //$value        = explode(",", $data[$fieldKey]);
        $value        = Strs::explodeToInt($data[$fieldKey]);
        $tempFieldKey = $this->rtrimId($fieldKey);
        $requiredKeys = [$fieldKey, "{$tempFieldKey}_name", "{$tempFieldKey}_avatar"];
        if (count(array_intersect_key($data, array_flip($requiredKeys))) === count($requiredKeys)) {
            [$userId, $userName, $avatar] = $requiredKeys;
            return $data[$userId] ? [
                "value" => $value,
                "text"  => [["id" => $data[$userId], "name" => $data[$userName], "avatar" => $data[$avatar]]]
            ] : ["value" => [], "text" => []];
        }
        $this->queryTypeValue["contact"][] = $data[$fieldKey];
        return array_filter($value);
    }

    public function valueByDepartment($data, $fieldKey)
    {
        //$value      = explode(",", $data[$fieldKey]);
        $value        = Strs::explodeToInt($data[$fieldKey]);
        $tempFieldKey = $this->rtrimId($fieldKey);
        $requiredKeys = [$fieldKey, "{$tempFieldKey}_name", "{$tempFieldKey}_pid_path_name"];
        if (count(array_intersect_key($data, array_flip($requiredKeys))) === count($requiredKeys)) {
            [$departmentId, $departmentName, $departmentPidPathName] = $requiredKeys;
            return $data[$departmentId] ? [
                "value" => $value,
                "text"  => [
                    ["id" => $data[$departmentId], "department_name" => $data[$departmentName], "full_path" => $data[$departmentPidPathName]]
                ]
            ] : ["value" => [], "text" => []];
        }

        $this->queryTypeValue["department"][] = $value;
        return array_filter($value);
    }

    public function valueByCustomerContact($data, $fieldKey)
    {
        //$value      = explode(",", $data[$fieldKey]);
        $value        = Strs::explodeToInt($data[$fieldKey]);
        $tempFieldKey = $this->rtrimId($fieldKey);
        $requiredKeys = [$fieldKey, "{$tempFieldKey}_name", "{$tempFieldKey}_phone"];
        if (count(array_intersect_key($data, array_flip($requiredKeys))) === count($requiredKeys)) {
            [$contactId, $contactName, $contactPhone] = $requiredKeys;
            return $data[$contactId] ? [
                "value" => $value,
                "text"  => [["id" => $data[$contactId], "name" => $data[$contactName], "phone" => $data[$contactPhone]]]
            ] : ["value" => [], "text" => []];
        }
        $this->queryTypeValue["customer_contact"][] = $data[$fieldKey];
        return array_filter($value);

    }

    public function valueByRadioCustomer($data, $fieldKey)
    {
        $value        = $data[$fieldKey] ?? 0;
        $tempFieldKey = $this->rtrimId($fieldKey);
        $requiredKeys = [$fieldKey, "{$tempFieldKey}_name"];
        if (count(array_intersect_key($data, array_flip($requiredKeys))) === count($requiredKeys)) {
            [, $customerName] = $requiredKeys;
            return ["value" => $value, "text" => $data[$customerName] ?? ""];
        }
        $this->queryTypeValue["radio_customer"][] = $data[$fieldKey];
        return $value;

    }

    public function valueByRadioProject($data, $fieldKey)
    {
        $value        = $data[$fieldKey] ?? 0;
        $tempFieldKey = $this->rtrimId($fieldKey);
        $requiredKeys = [$fieldKey, "{$tempFieldKey}_name"];
        if (count(array_intersect_key($data, array_flip($requiredKeys))) === count($requiredKeys)) {
            [, $projectName] = $requiredKeys;
            return ["value" => $value, "text" => $data[$projectName] ?? ""];
        }
        $this->queryTypeValue["radio_project"][] = $data[$fieldKey];
        return $value;

    }

    public function valueByRadioOrder($data, $fieldKey)
    {
        $value        = $data[$fieldKey] ?? 0;
        $tempFieldKey = $this->rtrimId($fieldKey);
        $requiredKeys = [$fieldKey, "{$tempFieldKey}_name"];
        if (count(array_intersect_key($data, array_flip($requiredKeys))) === count($requiredKeys)) {
            [, $orderName] = $requiredKeys;
            return ["value" => $value, "text" => $data[$orderName] ?? ""];
        }
        $this->queryTypeValue["radio_order"][] = $data[$fieldKey];
        return $value;

    }

    //值第一次格式化 end

    //值第二次格式化前查询 start
    public function queryContactValue($values)
    {
        $ids = $this->queryTypeValueBy($values);
        return $ids ? ValueCompile::contactQuery($ids)->keyBy("id")->toArray() : [];
    }

    public function queryCustomerContactValue($values)
    {
        $ids = $this->queryTypeValueBy($values);
        return $ids ? ValueCompile::customerContactQuery($ids)->keyBy("id")->toArray() : [];
    }

    public function queryDepartmentValue($values)
    {
        $ids = $this->queryTypeValueBy($values);
        return $ids ? ValueCompile::departmentQuery($ids)->toArray() : [];
    }

    public function queryRadioCustomerValue($values)
    {
        $ids = $this->queryTypeValueBy($values);
        return $ids ? ValueCompile::customerQuery($ids)->pluck("name", "id")->toArray() : [];
    }

    public function queryRadioProjectValue($values)
    {
        $ids = $this->queryTypeValueBy($values);
        return $ids ? ValueCompile::projectQuery($ids)->pluck("name", "id")->toArray() : [];
    }

    public function queryRadioOrderValue($values)
    {
        $ids = $this->queryTypeValueBy($values);
        return $ids ? ValueCompile::orderQuery($ids)->pluck("name", "id")->toArray() : [];
    }

    public function queryTypeValueBy($values)
    {
        $mergeValue = [];
        foreach ($values as $v) {
            $v            = is_array($v) ? $v : [$v];
            $mergeValue[] = $v;
        }
        return array_filter(array_unique(array_merge(...$mergeValue)));
    }
    //值第二次格式化前查询 end

    //值第二次格式化-通过查询出来的值（queryTypeValueBy()）再进行赋值
    public function queryTextBy($value, $fieldValueIndex)
    {
        $text = [];
        if (is_array($value)) {
            foreach ($value as $v) {
                isset($fieldValueIndex[$v]) && $text[] = $fieldValueIndex[$v];
            }
        } else {
            $text = $fieldValueIndex[$value] ?? "";
        }
        return $text;

    }

    //去除指定类型字段key的右侧id
    public function rtrimId($fieldKey)
    {
        return preg_replace('/_id$/', '', $fieldKey);
    }

    //获取表单模型
    public function getModel(): ?string
    {
        if (isset($this->form) && !isset($this->model)) {
            $this->model = CustomForm::getFormInfo($this->form)->model ?? "";
        }

        return $this->model ? "App\\Models\\{$this->model}" : null;
    }
}
