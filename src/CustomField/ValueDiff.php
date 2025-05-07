<?php

namespace lanerp\dong\CustomField;


class ValueDiff
{
    protected array $fieldType = [
        "input"                     => "default",//文本
        "textarea"                  => "default",//多行文本
        "date"                      => "default",//日期
        "attendance_date"           => "default",//日期
        "id_card"                   => "default",//身份证
        "paying_teller"             => "default",//收付款账户
        "number"                    => "number",//数字
        "money"                     => "number",//金额
        "compute_mode"              => "number",//计算工式
        "money_compute_mode"        => "number",//计算工式
        "order_amount_compute_mode" => "number",//订单金额计算工式
        "rate"                      => "number",//评分
        "radio"                     => "radio",//单选
        "radio_customer"            => "radio",//关联客户
        "radio_project"             => "radio",//关联商机
        "radio_project_stage"       => "radio",//商机阶段
        "checkbox"                  => "checkbox",//多选
        "cascader"                  => "checkbox",//级联
        "contact"                   => "checkbox",//联系人
        "department"                => "checkbox",//部门
        "customer_contact"          => "checkbox",//客户联系人
        "telephone"                 => "telephone",//电话
        "date_range"                => "dateRange",//日期区间
        "address"                   => "address",//地址
        "checkbox_approval"         => "checkbox",//关联审批单
        "checkbox_check_record"     => "checkbox",//关联打卡记录
        "system_file"               => "file",//系统文件
        "file"                      => "file",//文件
        "image"                     => "file",//图片
        "invoice"                   => "file",//发票
        "table"                     => "table",//表格
        //"serial_number"            => "default",//流水号
    ];

    protected static ValueDiff $instances;
    private array              $formFields;
    private array              $newData;
    private array              $oldData;
    private                    $field;

    public function __construct($newData, $oldData, $formFields = null)
    {
        $this->formFields = $formFields ?? Form::init()->formFields;
        $this->newData    = $newData;
        $this->oldData    = $oldData;
    }

    /**
     * Notes:
     * Date: 2024/12/18
     * @param $newData
     * @param $oldData
     * @param $formFields
     * @return ValueDiff|static
     */
    public static function init($newData, $oldData, $formFields = null)
    {
        return static::$instances ?? (static::$instances = new static($newData, $oldData, $formFields));
    }

    public function get(): array
    {
        return $this->dataDiff();
    }

    public function dataDiff(): array
    {
        $diffDatas  = [];
        $formFields = $this->formFields;
        foreach ($formFields as $field) {
            if ($field["field_type"] === "system") continue;
            $type = $this->fieldType[$field["field_type"]] ?? "default";
            //if ($type === null) continue;
            $diffData = [
                'field_key'  => $field['field_key'],
                'field_name' => $field['field_name'],
                'field_type' => $field['field_type'],
            ];
            $extend   = $field['extend'] ?? [];
            //isset($extend["visible_person_range"]) && $diffData["visible_person_range"] = $extend["visible_person_range"];
            isset($extend["num_display_style"]) && $diffData["num_display_style"] = $extend["num_display_style"];

            $newValue = $this->newData[$field["field_key"]] ?? null;
            $oldValue = $this->oldData[$field["field_key"]] ?? null;

            $this->field = $field;
            [$isDiff, $diffData['field_value']] = $this->$type($newValue, $oldValue);
            $isDiff && $diffDatas[] = $diffData;
        }
        //dd($diffDatas);
        return $diffDatas;
    }

    public function default($newValue, $oldValue)
    {
        $isDiff       = false;
        $newDiffValue = $newValue["value"] ?? "";
        $oldDiffValue = $oldValue["value"] ?? "";
        if ($newDiffValue !== $oldDiffValue) $isDiff = true;

        return [$isDiff, ["new" => $newValue, "old" => $oldValue]];

    }

    public function number($newValue, $oldValue)
    {
        $isDiff       = false;
        $newDiffValue = (float)($newValue["value"] ?? 0);
        $oldDiffValue = (float)($oldValue["value"] ?? 0);
        if ($newDiffValue !== $oldDiffValue) $isDiff = true;

        return [$isDiff, ["new" => $newValue, "old" => $oldValue]];
    }

    public function checkbox($newValue, $oldValue)
    {
        $isDiff       = false;
        $newDiffValue = $newValue["value"] ?? [];
        $oldDiffValue = $oldValue["value"] ?? [];
        if (count(array_diff($newDiffValue, $oldDiffValue)) ||
            count(array_diff($oldDiffValue, $newDiffValue))
        ) $isDiff = true;

        return [$isDiff, ["new" => $newValue, "old" => $oldValue]];
    }

    public function radio($newValue, $oldValue)
    {
        $isDiff       = false;
        $newDiffValue = $newValue["value"] ?? 0;
        $oldDiffValue = $oldValue["value"] ?? 0;
        if ($newDiffValue !== $oldDiffValue) $isDiff = true;

        return [$isDiff, ["new" => $newValue, "old" => $oldValue]];
    }

    public function address($newValue, $oldValue)
    {
        $isDiff     = false;
        $newText    = $newValue["text"] ?? "";
        $newAddress = $newValue["detail_address"] ?? "";
        $oldText    = $oldValue["text"] ?? "";
        $oldAddress = $oldValue["detail_address"] ?? "";
        if ($newText !== $oldText || $newAddress !== $oldAddress) $isDiff = true;

        return [$isDiff, ["new" => $newValue, "old" => $oldValue]];
    }

    public function telephone($newValue, $oldValue)
    {
        $isDiff       = false;
        $newDiffValue = $newValue["value"] ?? "";
        $newPrefix    = $newValue["prefix"] ?? "";
        $oldDiffValue = $oldValue["value"] ?? "";
        $oldPrefix    = $oldValue["prefix"] ?? "";
        if ($newDiffValue !== $oldDiffValue || $newPrefix !== $oldPrefix) $isDiff = true;

        return [$isDiff, ["new" => $newValue, "old" => $oldValue]];
    }

    public function dateRange($newValue, $oldValue)
    {
        $isDiff       = false;
        $newDiffValue = $newValue["value"] ?? [];
        $oldDiffValue = $oldValue["value"] ?? [];
        if (count(array_diff($newDiffValue, $oldDiffValue))) $isDiff = true;

        return [$isDiff, ["new" => $newValue, "old" => $oldValue]];
    }

    //public function relate_approve_form($newValue, $oldValue)
    //{
    //    $newIds = array_column($newValue, "approval_id") ?? [];
    //    $oldIds = array_column($oldValue, "approval_id") ?? [];
    //    $isDiff = false;
    //    if (count(array_diff($newIds, $oldIds)) || count(array_diff($oldIds, $newIds))) $isDiff = true;
    //
    //    return [$isDiff, ["new" => $newValue, "old" => $oldValue]];
    //}

    public function file($newValue, $oldValue)
    {
        $isDiff = false;
        $newValue ? ksort($newValue) : $newValue = [];
        $oldValue ? ksort($oldValue) : $oldValue = [];
        if (md5(json_encode($newValue)) !== md5(json_encode($oldValue))) {
            $isDiff = true;
        }
        return [$isDiff, ["new" => $newValue, "old" => $oldValue]];
    }

    public function table($newValue, $oldValue)
    {
        $isDiff   = false;
        $newValue = $newValue ?: [];
        $oldValue = $oldValue ?: [];
        if (md5(json_encode($newValue)) !== md5(json_encode($oldValue))) {
            $isDiff   = true;
            $newValue = FieldValueMerge::table($newValue, $this->field);
            $oldValue = FieldValueMerge::table($oldValue, $this->field);
        }
        return [$isDiff, ["new" => $newValue, "old" => $oldValue]];
    }
}
