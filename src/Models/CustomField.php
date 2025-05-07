<?php

namespace App\Models;

use App\Helpers\Arrs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use JetBrains\PhpStorm\NoReturn;

/**
 * This is the model class for table "custom_fields".
 *
 * @property   int    $id
 * @property   int    $company_id        公司 id
 * @property   string $field_form        字段所在表
 * @property   string $field_key         字段key
 * @property   string $field_name        字段名
 * @property   string $field_type        字段类型
 * @property   string $p_field_key       父级字段key
 * @property   string $quote_field_form  引用字段表
 * @property   string $quote_field_key   引用字段key
 * @property   int    $is_system         是否系统字段 0=否 1=是
 * @property   int    $is_system_key     是否是系统字段key(变更) 0=否,变更 1=是,不变更
 * @property   int    $is_reset          是否可以重置 0=否 1=是
 * @property   int    $is_unique         数据是否唯一 0=否 1=是
 * @property   int    $is_required       数据是否必填 0=否 1=是
 * @property   string $extend            字段扩展信息
 * @property   string $rules             显隐设置规则
 * @property   int    $status            是否禁用 0=否 1=是
 * @property   string $deleted_at
 * @property   string $created_at
 * @property   string $updated_at
 */
class CustomField extends Model
{

    protected $table = 'custom_fields';

    protected $fillable = ['company_id', 'field_form', 'field_key', 'field_name', 'field_type', 'p_field_key', 'quote_field_form', 'quote_field_key', 'is_system', 'is_system_key', 'is_reset', 'is_unique', 'is_required', 'extend', 'rules', 'status'];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'extend' => 'json',
        'rules'  => 'json',
    ];

    public const SYSTEM         = 1;
    public const NON_SYSTEM     = 0;
    public const SYSTEM_KEY     = 1;
    public const NON_SYSTEM_KEY = 0;
    public const RESET          = 1;
    public const NON_RESET      = 0;

    private int   $company_id;
    private mixed $fieldForm;
    private bool  $filterExtend          = false;
    private bool  $filterRules           = false;
    private bool  $filterFieldTypeSystem = false;
    private array $filterFieldTypes      = [];
    private array $formVersion           = [];

    protected static function booted()
    {
        parent::booted();
        static::addGlobalScope(new \lanerp\dong\Models\Scopes\SoftDeleteScope);
    }

    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Notes:获取字段
     * @param string $type   default
     * @param string $prefix 前缀
     * @return array
     */
    public static function columns(string $type = "", string $prefix = "", $pkId = null): array
    {
        $pkId    = $pkId ?: 'id as field_id';
        $columns = [
            "default" => [$pkId, 'company_id', 'field_form', 'field_key', 'field_name', 'field_type', 'p_field_key', 'quote_field_form', 'quote_field_key', 'is_system', 'is_system_key', 'is_reset', 'is_unique', 'is_required', 'extend', 'rules', 'status'],
            "quote"   => [$pkId, 'field_form', 'field_key', 'field_name', 'field_type', 'is_system', 'is_reset', 'extend'],
        ];
        $columns = $columns[$type] ?? $columns["default"];
        return Arrs::unshiftPrefix($columns, $prefix);
    }

    public static function initForm($fieldForm, $companyId = null)
    {
        $model             = new static();
        $model->company_id = $companyId ?? authUser()->company_id;
        $model->fieldForm  = $fieldForm;
        return $model;
    }

    public function setCompanyId($companyId): CustomField
    {
        $this->company_id = $companyId;
        return $this;
    }

    public function filterExtend(): CustomField
    {
        $this->filterExtend = true;
        return $this;
    }

    public function filterRules(): CustomField
    {
        $this->filterRules = true;
        return $this;
    }

    public function filterFieldTypeSystem(): CustomField
    {
        $this->filterFieldTypeSystem = true;
        return $this;
    }

    public function filterFieldTypeFormModule(): CustomField
    {
        $this->filterFieldTypes[] = "form_module";
        return $this;
    }

    public function filterFieldTypeControl(): CustomField
    {
        $this->filterFieldTypes[] = "control";
        return $this;
    }

    //生成表单版本（先调getFields()方法查询字段）
    public function getFormVersion(): string
    {
        $formVersion = "";
        if ($this->formVersion) {
            $formVersion = $this->formVersion;
            sort($formVersion);
            $formVersion = hash('md5', json_encode($formVersion));
        }
        return $formVersion;
    }

    /**
     * Notes:获取表单字段（全）
     * Date: 2024/12/10
     * @param bool $toTree
     * @return array
     */
    public function getFields(bool $toTree = false): array
    {
        $fields       = static::columns("", $prefix = "cf");
        $fields       = array_merge($fields, ['cfs.sort']);
        $filterFields = [];
        if ($this->filterExtend) $filterFields[] = "{$prefix}.extend";
        if ($this->filterRules) $filterFields[] = "{$prefix}.rules";
        $fields     = array_diff($fields, $filterFields);
        $orderByRaw = "CASE WHEN cf.field_type = 'system' THEN 1 ELSE 0 END ASC,";
        if ($this->filterFieldTypeSystem) {
            $where[]    = ['cf.field_type', '<>', "system"];
            $orderByRaw = "";
        }
        $where['cf.field_form'] = $this->fieldForm;
        $where[]                = [
            function ($query) {
                $query->where("cf.company_id", $this->company_id)->orWhere('cf.is_system', static::SYSTEM);
            }];
        $formFields             = static::query()->from("custom_fields as cf")
            ->select($fields)
            ->leftJoin("custom_field_sorts as cfs", fn($join) => $join->on('cfs.field_id', '=', 'cf.id')->where("cfs.company_id", $this->company_id))
            ->where($where)
            ->orderByRaw($orderByRaw . "cfs.sort asc,cf.sort asc,cf.id asc")
            ->get()
            ->transform(function ($row) {
                //!$this->filterExtend && $row->extend = json_decode($row->extend, true) ?? (object)[];
                //!$this->filterRules && $row->rules = json_decode($row->rules, true) ?? [];
                if (!in_array($row->field_type, ["system", "form_module"], true)) {
                    $this->formVersion[] = "{$row->field_key}:{$row->field_type}";
                }
                return $row;
            });
        CustomFieldReset::fieldsReset($formFields, $this->company_id);
        if ($this->filterFieldTypes) static::filterFieldTypes($formFields, $this->filterFieldTypes);
        if (!$this->filterExtend) {
            $quoteFieldIndex = $this->getQuoteFieldIndex($formFields);
            foreach ($formFields as &$val) {
                //如果有引用字段,变更为引用字段数据
                if ($val["quote_field_form"] !== "" && $val['quote_field_key'] !== '') {
                    $key = $val['quote_field_form'] . "-" . $val['quote_field_key'];
                    if (isset($quoteFieldIndex[$key])) {
                        $quoteField             = $quoteFieldIndex[$key];
                        $val['quote_extend']    = $quoteField['extend'] ?? (object)[];
                        $val['quote_is_system'] = $quoteField['is_system'];
                        $val['field_type']      = $quoteField['field_type'];
                        //$val['field_name']      = $quoteField->field_name;
                    }
                }
            }
        }
        //过滤表单组类型字段有可能会使索引不连贯-重新索引数组（可选）
        $formFields = array_values($formFields->toArray());
        return $toTree ? Arrs::listToTree($formFields, 'field_key', 'p_field_key', '_child', '', true) : $formFields;
    }


    /**
     * Notes:获取字段引用索引
     * Date: 2024/3/6
     * @param $fields
     * @return array
     */
    public function getQuoteFieldIndex($fields)
    {
        $fieldIndex     = [];
        $quoteFieldKeys = [];
        foreach ($fields as $v) {
            if ($v["quote_field_form"] !== "" && $v["quote_field_key"] !== "") {
                $quoteFieldKeys[$v["quote_field_form"]][] = $v["quote_field_key"];
            }
        }
        if (!empty($quoteFieldKeys)) {
            $model = static::query();
            foreach ($quoteFieldKeys as $quoteFieldForm => $quoteFieldKey) {
                $model = $model->orWhere(function ($query) use ($quoteFieldForm, $quoteFieldKey) {
                    $query->where("field_form", $quoteFieldForm)->whereIn("field_key", $quoteFieldKey);
                });
            }
            $fields = $model->select(static::columns("quote"))->get();
            $fields = CustomFieldReset::fieldsReset($fields, $this->company_id);
            foreach ($fields as $v) {
                $key              = $v->field_form . "-" . $v->field_key;
                $fieldIndex[$key] = $v;
            }
        }
        return $fieldIndex;
    }

    /**
     * Notes:过滤表单组或控件组类型字段
     * Date: 2024/10/28
     * @param $fields
     * @param $filterFieldTypes
     * @return void
     */
    private static function filterFieldTypes($fields, $filterFieldTypes): void
    {
        $filterFieldKeys = $fields->filter(fn($field) => in_array($field['field_type'], $filterFieldTypes, true))->pluck('field_key')->all();
        if ($filterFieldKeys) {
            //可加&可不加&
            foreach ($fields as $k => $v) {
                if (in_array($v->field_key, $filterFieldKeys, true)) {
                    $fields->forget($k);// 从 $fields 中删除 $v
                }
                if (in_array($v->p_field_key, $filterFieldKeys, true)) {
                    $v->p_field_key = "";
                }

            }
        }
        return;
    }


    /**
     * Notes:查询表单字段（一个表单只查询一次）
     * Date: 2024/11/20
     * @param $form
     * @return mixed
     */
    public static function queryFields($form): mixed
    {
        static $formFields;

        if (!$form) return collect([]);

        if (isset($formFields[$form])) {
            $formField = $formFields[$form];
        } else {
            $formField = static::query()
                ->select(['id as field_id', 'field_key', 'p_field_key', 'field_type', 'status', 'is_reset', 'is_system'])
                ->where([
                    "field_form" => $form,
                    [fn($query) => $query->where("company_id", authUser()->company_id)->orWhere('is_system', static::SYSTEM)],
                    ['field_type', '<>', "system"]
                ])
                ->get();
            static::filterFieldTypes($formField, ["form_module", "control"]);
            $resetFieldIds = $formField->filter(fn($field) => $field['is_reset'] === 1)->pluck('field_id')->all();
            if ($resetFieldIds) {
                $resetFields = CustomFieldReset::query()->select(["status"])
                    ->where(["company_id" => authUser()->company_id])->whereIn("field_id", $resetFieldIds)
                    ->get()->keyBy("field_id");
                if ($resetFields) {
                    //可加&可不加&
                    foreach ($formField as &$v) {
                        if ($resetField = $resetFields->get($v->field_id)) {
                            $v->status = $resetField->status;
                        }
                    }
                }
            }

            $formFields[$form] = $formField;
        }

        return $formField ?: [];
    }

    //获取系统字段
    public static function getFieldBySystem($form)
    {
        return static::queryFields($form)->filter(fn($field) => $field['is_system'] === 1);
    }

    //通过字段类型获取字段
    public static function getFieldByType($form, $fieldType)
    {
        return static::queryFields($form)->filter(fn($field) => in_array($field['field_type'], $fieldType, true) && $field['p_field_key'] === "");
    }

    /**
     * Notes:获取字段key
     * Date: 2025/4/10
     * @param      $form
     * @param bool $isGroupBySystem 是否系统非系统字段分组
     * @return null[]
     */
    public static function getFieldKeys($form, bool $isGroupBySystem = false)
    {
        $fields = static::queryFields($form);
        if ($isGroupBySystem) {
            $fields = $fields->groupBy("is_system");
            $fields = [$fields->get(static::SYSTEM)?->pluck("field_key")->all() ?? [], $fields->get(static::NON_SYSTEM)?->pluck("field_key")->all() ?? []];
        } else {
            $fields = $fields->pluck("field_key")->all();
        }
        return $fields;
    }

}
