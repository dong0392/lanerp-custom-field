<?php

namespace lanerp\dong\CustomField;


use lanerp\common\Helpers\Arrs;
use App\Models\CustomField;
use App\Models\CustomFieldFormSnapshot;
use App\Models\CustomForm;
use Common\Helpers\Utils;
use Common\Model\FieldFormSnapshotModel;
use Common\Model\FieldModel;
use Common\Model\FieldTableModel;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;


/**
 * 表单相关公共方法类.
 *
 * @property   string  $table             数据库表名
 * @property   string  $form              自定义表单名
 * @property   string  $model             数据库表名对应的类模型
 * @property   string  $pk                数据库表名主键名称
 * @property   array   $fields            初始查询出的所有字段
 * @property   array   $fieldValues       表单所有字段所对应的值
 * @property   array   $formFields        初始查询出的所有自定义字段生成表单后的字段格式
 * @property   array   $fieldIndex        初始查询出的所有自定义字段进行了`field_key`索引
 * @property   int     $companyId         初始公司ID
 * @property   int     $uid               初始用户id
 * @property   int     $approvalType      审批类型
 * @property   int     $approvalSetId     审批类型设置id
 * @property   array   $extendValue       表单扩展字段所对应的值
 * @property   array   $withCompanyId     数据库表是否存在公司id
 * @property   array   $systemField       初始查询出的所有系统字段
 * @property   array   $extendField       初始查询出的所有自定义字段
 * @property   array   $systemFieldValue  表单系统字段所对应的值
 * @property   array   $extendFieldValue  表单扩展字段所对应的值
 * @property   array   $logicField        表单需要处理业务的字段
 * @property   array   $uniqueField       表单字段有唯一的属性
 *
 * @property   Request $request           请求类(方法直接获取传参)$this->request->方法
 *
 * @method  static _init() 初始化表单
 * @method         _validateUnique() 验证表单数据唯一
 * @method         _getSystemFieldValue() 获取系统字段值
 * @method         _getExtendFieldValue() 获取扩展字段值
 * @method         _collect() 获取表单所有传参的值
 * @method         _create() 数据库插入数据
 * @method         _update() 数据库更新数据-直接更新
 * @method         _fillSave() 数据库先查询验证, 再更新数据-通过模型更新
 */
class Form
{
    protected static $instances;
    private string   $table;
    private string   $form;
    private string   $model;
    public string    $pk;
    public array     $fields             = [];
    public array     $fieldValues        = [];
    public array     $formFields         = [];
    public array     $fieldIndex         = [];
    private          $uid;
    public           $companyId;
    public string    $approvalType;
    private int      $approvalSetId      = 0;
    private array    $extendValue        = [];//扩展字段值
    public bool      $withCompanyId;//判断表单是否带公司id
    public bool      $withUid;//判断表单是否带uid
    public bool      $withDeletedAt;//判断表单是否带删除
    public array     $systemField        = [];
    private array    $systemFieldValue   = [];
    public array     $extendField        = [];
    private array    $extendFieldValue   = [];
    public array     $logicField         = [];
    public array     $uniqueField        = [];
    public array     $snField            = [];//流水号字段
    public array     $snFieldValue       = [];//流水号字段值
    public array     $invoiceField       = [];//发票-扩展字段
    public array     $workorderRoleField = [];//工单角色-扩展字段
    public array     $eventControl       = [];//获取需求处理业务的控件组
    public string    $formVersion        = "";//当前表单生成的版本
    public array     $fieldKeyToParent   = [];//字段key的父级
    public ?string   $formSnapshotKey    = null;//表单字段生成快照key
    //public array   $fieldInvoice     = [];//发票表 插入数据
    //public array    $frontEventControl = [];//获取需求处理业务的前置控件组
    public FormAssistance $formAssistance;//表单辅助类
    public ControlEvent   $controlEvent;//事件触发类
    public Request        $request;//请求类

    public function __construct(string $form, $uid, $companyId, $fields)
    {
        if ($form) {
            $formInfo    = CustomForm::getFormInfo($form);
            $this->table = $formInfo->table ?? "";
            $this->model = $formInfo->model ?? "";
            $this->pk    = $formInfo->pk ?? "id";
        }
        $this->form          = $form;
        $this->uid           = $uid ?? user()->id;
        $this->companyId     = $companyId ?? user()->company_id;
        $this->withCompanyId = $this->withUid = $this->withDeletedAt = true;
        $this->fields        = $fields;
        $this->genFormField();
        $this->request        = request();
        $this->formAssistance = new FormAssistance($this);
        $this->controlEvent   = new ControlEvent($this);
        $this->validateVersionMatch();
    }

    //验证表单版本是否匹配
    public function validateVersionMatch(): void
    {
        if (($formVersion = $this->request->header("form-version")) && $formVersion !== $this->formVersion) {
            _throwException("当前页面可能停留时间过长，表单已被管理员修改，请保存或刷新页面。", 9997);
        }
    }

    /**
     * Notes:创建字段模型，可以在多处使用Field::init()调用其方法
     * Date: 2024/10/28
     * @param string $form
     * @param        $uid
     * @return static
     */
    public static function init(string $form = "", $uid = null, $companyId = null, $fields = []): static
    {
        return static::$instances ?? (static::$instances = new static($form, $uid, $companyId, $fields));
    }

    /**
     * Notes:创建新的字段模型，需要一次执行完
     * Date: 2024/10/28
     * @param string $form
     * @param        $uid
     * @return static
     */
    public static function newInit(string $form = "", $uid = null, $companyId = null, $fields = []): static
    {
        return new static($form, $uid, $companyId, $fields);
    }

    //重置uid
    public function setUid($uid): Form
    {
        $this->uid = $uid;
        return $this;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    //重置公司id
    public function setCompanyId($companyId): Form
    {
        $this->companyId = $companyId;
        return $this;
    }

    //获取用户信息
    public function getUser(): array
    {
        return ["uid" => $this->uid, "company_id" => $this->companyId];
    }

    //设置实际表名
    public function setForm(string $form): Form
    {
        $this->form = $form;
        return $this;
    }

    //设置实际表名
    public function setTable(string $table): Form
    {
        $this->table = $table;
        return $this;
    }

    //设置Model
    public function setModel(string $model): Form
    {
        $this->model = $model;
        return $this;
    }

    public function getTable(): string
    {
        return $this->table ?? "";
    }

    //获取字段表名
    public function getForm(): string
    {
        return $this->form;
    }

    //获取表单模型
    public function getModel(): string
    {
        return "App\\Models\\{$this->model}";
    }

    //设置审批类型
    public function setApprovalType(string $approvalType): Form
    {
        $this->approvalType = $approvalType;
        return $this;
    }

    //设置审批类型id
    public function setApprovalSetId($approvalSetId): Form
    {
        $this->approvalSetId = $approvalSetId;
        return $this;
    }

    public function getApprovalSetId(): int
    {
        return $this->approvalSetId;
    }

    public function setWithCompanyId($isWithCompanyId = false): Form
    {
        $this->withCompanyId = $isWithCompanyId;
        return $this;
    }

    public function setWithUid($isWithUid = false): Form
    {
        $this->withUid = $isWithUid;
        return $this;
    }

    public function setWithDeletedAt($isWithDeletedAt = false): Form
    {
        $this->withDeletedAt = $isWithDeletedAt;
        return $this;
    }

    //获取系统字段值
    public function getSystemFieldValue(): array
    {
        if (!$this->systemFieldValue) {
            $systemFieldValue = $this->request->only($this->systemField);
            foreach ($systemFieldValue as $key => $value) {
                //if (!is_array($value)) dd($key,$value);
                $this->systemFieldValue[$key] = array_key_exists("value", $value) ? $value["value"] : $value;
                if (isset($value["value"]) && is_array($value["value"]) && in_array($this->fieldIndex[$key]["field_type"], ["contact", "department", "customer_contact"])) {
                    $this->systemFieldValue[$key] = implode(",", $value["value"]);
                } elseif ($this->fieldIndex[$key]["field_type"] === "address") {
                    if ($key === "country_region") {
                        //只有$key === "country_region"时，这个数库表单这些数据是固定的
                        $this->systemFieldValue["country"]        = $value["value"][0] ?? 0;
                        $this->systemFieldValue["province"]       = $value["value"][1] ?? 0;
                        $this->systemFieldValue["city"]           = $value["value"][2] ?? 0;
                        $this->systemFieldValue["area"]           = $value["value"][3] ?? 0;
                        $this->systemFieldValue["country_region"] = $value["text"] ?? "";
                        $this->systemFieldValue["addr"]           = $value["detail_address"] ?? "";
                    } else {
                        //$value值为[99999999,1,2]，99999999有可能国家，有可能是省，没法确认，只能在业务逻辑中获取确认
                        $this->systemFieldValue[$key . "_text"] = $value["text"] ?? "";
                        $this->systemFieldValue[$key . "_addr"] = $value["detail_address"] ?? "";
                    }
                } elseif ($this->fieldIndex[$key]["field_type"] === "telephone") {
                    $this->systemFieldValue[$key . "_prefix"] = $value["prefix"] ?? "";
                } elseif ($this->fieldIndex[$key]["field_type"] === "date_range_apm") {
                    $key                                          = explode("_", $key)[0];
                    $this->systemFieldValue[$key . "_start_date"] = $value["start_date"] ?? null;
                    $this->systemFieldValue[$key . "_start_time"] = $value["start_time"] ?? null;
                    $this->systemFieldValue[$key . "_end_date"]   = $value["end_date"] ?? null;
                    $this->systemFieldValue[$key . "_end_time"]   = $value["end_time"] ?? null;
                } elseif ($this->fieldIndex[$key]["field_type"] === "paying_teller") {
                    $key                                            = explode("_", $key)[0];
                    $this->systemFieldValue[$key . "_card_number"]  = $value["value"] ?? "";
                    $this->systemFieldValue[$key . "_account_name"] = $value["account_name"] ?? "";
                    $this->systemFieldValue[$key . "_bank_name"]    = $value["bank_name"] ?? "";
                    $this->systemFieldValue[$key . "_bank_branch"]  = $value["bank_branch"] ?? "";
                    $this->systemFieldValue[$key . "_account_type"] = $value["account_type"] ?? 0;
                } elseif ($this->fieldIndex[$key]["field_type"] === "money") {
                    $this->systemFieldValue[$key . "_converted"] = $value["value_converted"] ?? "";
                    $this->systemFieldValue[$key . "_currency"]  = $value["currency"] ?? "";
                } elseif ($this->fieldIndex[$key]["field_type"] === "money_compute_mode") {
                    $this->systemFieldValue[$key . "_converted"] = $value["value_converted"] ?? "";
                    $this->systemFieldValue[$key . "_currency"]  = $value["currency"] ?? "";
                }
            }
        }
        //调用了$this->genFieldSN()方法，流水号会提前加入到系统字段
        if (isset($this->snFieldValue[CustomField::SYSTEM])) {
            $this->systemFieldValue = array_merge($this->snFieldValue[CustomField::SYSTEM], $this->systemFieldValue);
        }
        return $this->systemFieldValue;
    }

    //获取扩展字段值
    public function getExtendFieldValue(): array
    {
        if (!$this->extendFieldValue) {
            $this->extendFieldValue = $this->request->only($this->extendField);
        }
        //调用了$this->genFieldSN()方法，流水号会提前加入到扩展字段
        if (isset($this->snFieldValue[CustomField::NON_SYSTEM])) {
            $this->extendFieldValue = array_merge($this->snFieldValue[CustomField::NON_SYSTEM], $this->extendFieldValue);
        }
        return $this->extendFieldValue;
    }

    //获取扩展字段值
    public function getWorkorderRoleFieldValue(): array
    {
        return $this->request->only($this->workorderRoleField);
    }

    //request数据合并（请求数据）
    public function requestMerge(array $input): Form
    {
        $this->request->merge($input);
        return $this;
    }

    /**
     * Notes:生成表单相关数据
     * Date: 2024/10/28
     */
    public function genFormField(): void
    {
        if ($this->form || $this->fields) {
            if (empty($this->fields)) {
                $customField       = CustomField::initForm($this->form);
                $this->fields      = $customField->setCompanyId($this->companyId)->filterRules()->getFields();
                $this->formVersion = $customField->getFormVersion();
            }
            foreach ($this->fields as $v) {
                $this->fieldIndex[$v['field_key']] = Arr::only($v, ["field_type", "is_system"]);
                if ($v['field_type'] === "system") {
                    $this->logicField[] = $v['field_key'];
                } else {
                    if ($v['is_system'] === CustomField::SYSTEM) {
                        $this->systemField[] = $v['field_key'];
                    }
                    if ($v['is_system'] === CustomField::NON_SYSTEM) {
                        $this->extendField[] = $v['field_key'];
                    }
                    if ($v['is_unique'] === 1) {
                        $this->uniqueField[] = ['field_key' => $v['field_key'], 'is_system' => $v['is_system'], 'field_name' => $v['field_name']];
                    }
                }
                if ($v['field_type'] === "serial_number") {
                    $this->snField[] = [
                        'is_system'           => $v['is_system'],
                        'field_key'           => $v['field_key'],
                        'serial_number_rules' => $v['extend']['serial_number_rules'] ?? [],
                    ];
                }
                if ($v['field_type'] === "invoice") {
                    $this->invoiceField[] = $v['field_key'];
                }
                if ($v['field_type'] === "workorder_role") {
                    $this->workorderRoleField[] = $v['field_key'];
                }
                if ($v['field_type'] === "control") {
                    $this->eventControl[] = ["event_control" => $v['field_key'], "quote_field_form" => $v['quote_field_form']];
                }
                //if ($v['field_type'] === "control" && in_array($v['field_key'], FieldTableModel::frontEventControl, true)) {
                //    // todo $this->frontEventControl[] = ["event_control" => $v['field_key'], "quote_field_table" => $v['quote_field_table'], "release_time" => time()];
                //}

                if (!in_array($v['field_type'], ["control", "form_module"])) {
                    $this->formFields[] = $v;
                }

                if ($v['p_field_key'] !== "") {
                    $this->fieldKeyToParent[$v['field_key']] = $v['p_field_key'];
                }
            }
            $this->formFields = Arrs::listToTree($this->formFields, 'field_key', 'p_field_key', '_child', '', true);
        }
    }

    /**
     * Notes:验证数据是否唯一
     * Date: 2024/10/30
     * @param string $pk //排除验证值的索引字段 如编辑有可能是id，有可能是xxx_id
     * @return bool
     */
    public function validateUnique(string $pk = "id"): bool
    {
        return $this->formAssistance->validateUnique($pk);
    }

    /**
     * Notes:获取所有传参的值
     * Date: 2024/10/30
     * @param array $otherFields 除获取表单本身所有字段的其它字段
     * @return \Illuminate\Support\Collection
     */
    public function collect(array $otherFields = []): \Illuminate\Support\Collection
    {
        if (!$this->fieldValues) {
            $this->fieldValues = array_merge($this->request->only(array_merge($otherFields, $this->logicField)), $this->getExtendFieldValue(), $this->getSystemFieldValue());
        }
        return collect($this->fieldValues);
    }

    /**
     * Notes:插入数据
     * Date: 2024/10/30
     * @param $systemData
     * @param $extendData
     * @return mixed
     */
    public function create($systemData, $extendData = null): mixed
    {
        return $this->formAssistance->create($systemData, $extendData);
    }

    /**
     * Notes:更新数据
     * Date: 2024/10/31
     * @param $pkId
     * @param $systemData
     * @param $extendData
     * @return mixed
     */
    public function update($pkId, $systemData, $extendData = null): mixed
    {
        return $this->formAssistance->update($pkId, $systemData, $extendData);
    }

    /**
     * Notes:先查询后更新数据
     * Date: 2024/10/31
     * @param \Illuminate\Database\Eloquent\Model|int $modelOrId 要更新的数据表模型或更新表的主键id
     * @param                                         $systemData
     * @param                                         $extendData
     * @return mixed
     */
    public function fillSave(\Illuminate\Database\Eloquent\Model|int $modelOrId, $systemData, $extendData = null): mixed
    {
        return $this->formAssistance->fillSave($modelOrId, $systemData, $extendData);
    }

    /**
     * Notes:流水号按规则生成
     * Date: 2024/11/26
     * @return array
     */
    public function genFieldSN()
    {
        if ($this->snField && !$this->snFieldValue) {
            $this->snFieldValue = $this->formAssistance->genFieldSN();
        }
        return $this->snFieldValue;
    }

    /**
     * Notes:审批表单信息存储（及触发一些业务逻辑信息存储） todo 审批新增时调用
     * Date: 2024/11/27
     * @param $approvalId
     * @return $this
     */
    public function approvalSave($approvalId, $reqFormInfo = null): self
    {
        $this->formAssistance->approvalSave($approvalId, $reqFormInfo);
        return $this;
    }

    /**
     * Notes:控件组事件触发
     * Date: 2024/11/26
     * @param $pkId
     * @return $this
     */
    public function controlEventTrigger($pkId): self
    {
        $this->controlEvent->controlEventTrigger($pkId);
        return $this;

    }

    /**
     * Notes:取消事件触发
     * Date: 2024/11/26
     * @param $pkId
     * @return $this
     */
    public function cancelEventTrigger($pkId): self
    {
        //审批取消-则将存入的发票记录进行删除（针对验证发票是否已经存在接口-existsInvoice）
        $this->formAssistance->invoiceDel($pkId);
        //取消控件组事件触发
        $this->controlEvent->cancelControlEventTrigger($pkId);
        return $this;
    }

    /**
     * Notes:控件组审批创建事件触发
     * Date: 2024/11/26
     * @param $pkId
     * @return $this
     */
    public function frontControlEventTrigger($pkId): self
    {
        $this->controlEvent->frontControlEventTrigger($pkId);
        return $this;

    }

    /**
     * Notes:表单字段生成快照
     * Date: 2025/1/3
     * @return false|string
     */
    public function genFormSnapshot(): string
    {
        //hash('sha256', $input) 64位
        $this->formSnapshotKey = hash('md5', "{$this->companyId}{$this->form}" . json_encode($this->fields));
        if (!CustomFieldFormSnapshot::query()->where(["company_id" => $this->companyId, "form" => $this->form, "form_snapshot_key" => $this->formSnapshotKey])->exists()) {
            CustomFieldFormSnapshot::create(["company_id" => $this->companyId, "form" => $this->form, "form_snapshot_key" => $this->formSnapshotKey, "form_fields" => $this->fields]);
        }
        return $this->formSnapshotKey;
    }


}
