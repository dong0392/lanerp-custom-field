<?php

namespace lanerp\dong\CustomField;

use lanerp\dong\Models\ApprovalFormInfo;
use lanerp\dong\Models\ApprovalRelateControl;
use lanerp\dong\Models\CustomField;
use lanerp\dong\Models\CustomFieldInvoice;
use lanerp\dong\Models\CustomFieldSnInc;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redis;
use Predis\Client;

class FormAssistance
{

    protected Form $form;

    public function __construct(Form $form)
    {
        $this->form = $form;
    }

    /**
     * Notes:验证数据是否唯一
     * Date: 2024/10/30
     * @param $pk
     * @return bool
     */
    public function validateUnique($pk): bool
    {
        /* @var \Illuminate\Database\Eloquent\Model $model */
        $model = $this->form->getModel();
        if (!class_exists($model)) {
            _throwException($model . "不存在");
        }
        if ($this->form->uniqueField) {
            $tempWhere = [];
            foreach ($this->form->uniqueField as $field) {
                $isSystem = $field["is_system"];
                $fieldKey = $field["field_key"];
                if ($isSystem === CustomField::SYSTEM) {
                    $value = $this->form->getSystemFieldValue()[$fieldKey] ?? "";
                    $value !== "" && $tempWhere[] = [$fieldKey, $value];
                }
                if ($isSystem === CustomField::NON_SYSTEM) {
                    $value = $this->form->getExtendFieldValue()[$fieldKey]["value"] ?? "";
                    $value !== "" && $tempWhere[] = ["extends->{$fieldKey}->value", $value];
                }
            }
            if ($tempWhere) {
                $where[] = [
                    function ($query) use ($tempWhere) {
                        foreach ($tempWhere as $where) {
                            $query->orWhere(...$where);
                        }
                    }];
                if ($this->form->withCompanyId) $where["company_id"] = $this->form->companyId;
                if ($this->form->withDeletedAt) $where["deleted_at"] = null;
                if ($pkId = $this->form->request->input($pk)) {
                    $where[] = [$this->form->pk, '<>', $pkId];
                }
                if ($model::query()->where($where)->exists()) {
                    _throwException(implode('或', Arr::pluck($this->form->uniqueField, "field_name")) . "，存在数据重复");
                }
            }
        }
        return true;
    }

    /**
     * Notes:插入数据
     * Date: 2024/10/30
     * @param $createData
     * @param $extendData
     * @return mixed
     */
    public function create($createData, $extendData = null): mixed
    {
        //如何表单有公司id,且插入数据没定义则默认带入
        if ($this->form->withUid) $createData["uid"] = $createData["uid"] ?? $this->form->getUid();
        if ($this->form->withCompanyId) $createData["company_id"] = $createData["company_id"] ?? $this->form->companyId;
        if ($this->form->formSnapshotKey) $createData["form_snapshot_key"] = $createData["form_snapshot_key"] ?? $this->form->formSnapshotKey;
        $extendData = $extendData ?? $this->form->getExtendFieldValue();
        //$createData["extends"] = json_encode($extendData, JSON_UNESCAPED_UNICODE);

        //生成流水号，并分别合并到系统字段与扩展字段
        $this->form->genFieldSN();
        if (isset($this->form->snFieldValue[CustomField::SYSTEM])) {
            $createData = array_merge($this->form->snFieldValue[CustomField::SYSTEM], $createData);
        }
        if (isset($this->form->snFieldValue[CustomField::NON_SYSTEM])) {
            $extendData = array_merge($this->form->snFieldValue[CustomField::NON_SYSTEM], $extendData);
        }
        $createData["extends"] = $extendData;
        //dd($createData);
        /* @var \Illuminate\Database\Eloquent\Model $model */
        $model = $this->form->getModel();
        return $model::create($createData);//todo 有需要可以在此统一调$this->form->controlEventTrigger($pkId);
    }

    /**
     * Notes:更新数据
     * Date: 2024/10/31
     * @param $pkId
     * @param $updateData
     * @param $extendData
     * @return mixed
     */
    public function update($pkId, $updateData, $extendData = null): mixed
    {
        if ($this->form->formSnapshotKey) $updateData["form_snapshot_key"] = $updateData["form_snapshot_key"] ?? $this->form->formSnapshotKey;
        $extendData            = $extendData ?? $this->form->getExtendFieldValue();
        $updateData["extends"] = $extendData;
        /* @var \Illuminate\Database\Eloquent\Model $model */
        $model                  = $this->form->getModel();
        $where[$this->form->pk] = $pkId;
        if ($this->form->withCompanyId) $where["company_id"] = $this->form->companyId;
        return $model::query()->where($where)->update($updateData);//todo 有需要可以在此统一调$this->form->controlEventTrigger($pkId);
    }

    /**
     * Notes:先查询后更新数据
     * Date: 2024/10/31
     * @param \Illuminate\Database\Eloquent\Model|int $modelOrId
     * @param                                         $updateData
     * @param                                         $extendData
     * @return mixed
     */
    public function fillSave(\Illuminate\Database\Eloquent\Model|int $modelOrId, $updateData, $extendData = null): mixed
    {
        /* @var \Illuminate\Database\Eloquent\Model $model */
        $model = $this->form->getModel();
        if (is_int($modelOrId)) {
            $where[$this->form->pk] = $modelOrId;
            if ($this->form->withCompanyId) $where["company_id"] = $this->form->companyId;
            if (!$modelOrId = $model::query()->where($where)->first()) _throwException("数据不存在");

        }
        if (!($modelOrId instanceof \Illuminate\Database\Eloquent\Model)) {
            _throwException("模型不正确");
        }
        if ($this->form->formSnapshotKey) $updateData["form_snapshot_key"] = $updateData["form_snapshot_key"] ?? $this->form->formSnapshotKey;
        //$extendData            = $extendData ?? $this->form->getExtendFieldValue();
        //$updateData["extends"] = array_merge($modelOrId->extends ?? [], $extendData);
        $updateData["extends"] = $extendData ?? array_merge($modelOrId->extends ?? [], $this->form->getExtendFieldValue());
        $model                 = $modelOrId->fill($updateData);
        if (!$model->save()) _throwException("数据保存失败");
        return $model;//todo 有需要可以在此统一调$this->form->controlEventTrigger($pkId);
    }

    /**
     * Notes:生成流水号
     * Date: 2024/11/12
     */
    public function genFieldSN(): array
    {
        //return [1 => ["serial_number" => "7788"], 0 => ["field_10062" => ["value" => "778899"]]];
        $sns = [];
        foreach ($this->form->snField as $v) {
            $fieldKey = $v['field_key'];
            $rules    = $v['serial_number_rules'];
            $isSystem = (int)$v['is_system'];

            //生成流水号前半部分
            $date       = date('ymd');
            $time       = date('Hi');
            $haveDate   = false;
            $length     = 0;
            $sn         = '';
            $replaceStr = '___&&&@@@';
            foreach ($rules as $rule) {
                switch ($rule['type']) {
                    case 'string':
                        $sn .= $rule['value'];
                        break;
                    case 'date':
                        $haveDate = true;
                        $sn       .= $date;
                        if ($rule['value'] === 'y-m-d-h-m') $sn .= $time;
                        break;
                    case 'number':
                        $length = $rule['value'];
                        $sn     .= $replaceStr;
                        break;
                    default:
                        $sn .= '';
                        break;
                }
            }

            //生成流水号自增部分 如果有日期按每天递增
            $redisKey = "sn_{$fieldKey}_{$this->form->getForm()}_{$this->form->companyId}";
            /* @var Client $redis */
            $redis = Redis::connection();
            $redis->select(10);
            $query = CustomFieldSnInc::query()->where(["field_form" => $this->form->getForm(), "field_key" => $fieldKey]);
            if ($haveDate) {
                $redisKey = "{$redisKey}_{$date}";
                if ($redis->exists($redisKey)) {
                    $query->where("inc_date", date("Y-m-d"))->increment("inc_num");
                    $incNum = $redis->incr($redisKey);
                } else {
                    /* @var CustomFieldSnInc $model */
                    $model = $query->whereNotNull("inc_date")->first(["id", "inc_num", "inc_date"]);
                    if (!$model) {
                        $incNum = 1;
                        CustomFieldSnInc::query()
                            ->insert(["company_id" => $this->form->companyId, "field_form" => $this->form->getForm(), "field_key" => $fieldKey, "inc_num" => $incNum, "inc_date" => date("Y-m-d")]);
                    } else {
                        if ($model->inc_date === date("Y-m-d")) {
                            $incNum = ++$model->inc_num;
                        } else {
                            $model->inc_num  = $incNum = 1;
                            $model->inc_date = date("Y-m-d");
                        }
                        $model->save();
                    }
                    $redis->setex($redisKey, 86400, $incNum);
                }
            } else {
                if ($redis->exists($redisKey)) {
                    $query->whereNull("inc_date")->increment("inc_num");
                    $incNum = $redis->incr($redisKey);
                } else {
                    $model = $query->whereNull("inc_date")->first(["id", "inc_num", "inc_date"]);
                    if (!$model) {
                        $incNum = 1;
                        CustomFieldSnInc::query()
                            ->insert(["company_id" => $this->form->companyId, "field_form" => $this->form->getForm(), "field_key" => $fieldKey, "inc_num" => $incNum]);
                    } else {
                        $incNum = ++$model->inc_num;
                        $model->save();
                    }
                    $redis->setex($redisKey, 86400 * 180, $incNum);
                }
            }
            $sn = str_replace($replaceStr, sprintf("%0{$length}d", $incNum), $sn);
            if ($isSystem === CustomField::SYSTEM) {
                $sns[$isSystem][$fieldKey] = $sn;
            } else {
                $sns[$isSystem][$fieldKey]["value"] = $sn;
            }
        }
        return $sns;
    }

    /**
     * Notes:审批表单信息存储（及触发一些业务逻辑信息存储）
     * Date: 2024/11/18
     * @param $approvalId
     * @return void
     */
    public function approvalSave($approvalId, $reqFormInfo = null): void
    {
        //审批请求信息存储
        /* @var ApprovalFormInfo $formInfo */
        $formInfo = ApprovalFormInfo::query()->where("approval_id", $approvalId)->first();
        if ($formInfo) {
            $formInfo->form_info     = array_merge($formInfo->form_info, ($reqFormInfo ?? $this->form->request->except(["node_list", "s"])));
            $formInfo->event_control = $this->form->eventControl;
            $formInfo->save();
            //删除关联控件
            ApprovalRelateControl::query()->where("approval_id", $approvalId)->delete();
        } else {
            $saveData ["company_id"]    = $this->form->companyId;
            $saveData ["approval_id"]   = $approvalId;
            $saveData ["form_info"]     = $reqFormInfo ?? $this->form->request->except(["node_list", "s"]);
            $saveData ["event_control"] = $this->form->eventControl;
            ApprovalFormInfo::create($saveData);
        }
        $this->invoiceSave($approvalId);
        $this->approvalRelateControl($approvalId);

        return;
    }

    /**
     * Notes:发票值存储
     * Date: 2024/1/30
     * @param $pkId
     */
    public function invoiceSave($pkId): void
    {
        if ($invoiceField = $this->form->invoiceField) {
            $data = array_merge(Arr::only($this->form->getSystemFieldValue(), $invoiceField), $this->form->getExtendFieldValue());
            //$data              = $this->form->request->except(["node_list","s"]);

            $invoiceFieldValue = [];
            function findValuesByKeys($array, $keysToFind, &$invoiceFieldValue)
            {
                foreach ($array as $key => $value) {
                    // 如果当前键是目标键之一，直接将其内容添加到结果数组中
                    if (in_array($key, $keysToFind, true)) {
                        //判断值是不是为空，且是不是发票类型的值
                        if (isset($value[0]["op"])) {
                            $invoiceFieldValue[$key] = array_merge($invoiceFieldValue[$key] ?? [], $value);
                        }
                        // 目标键已经找到并处理，跳过当前键的子项递归
                        continue;
                    }
                    // 如果当前值是数组，递归查找子项
                    if (is_array($value)) {
                        findValuesByKeys($value, $keysToFind, $invoiceFieldValue);
                    }
                }
            }

            //主要获取扩展字段就可以了，如果加上系统字段（遍历太多了）（系统字段只获取到一级，系统字段有发票控件的话自己处理）
            findValuesByKeys($data, $invoiceField, $invoiceFieldValue);

            //dd($invoiceFieldValue);

            $saveInvoices = [];
            foreach ($invoiceFieldValue as $fieldKey => $invoices) {
                $saveInvoice["uid"]             = $this->form->getUid();
                $saveInvoice["company_id"]      = $this->form->companyId;
                $saveInvoice["table"]           = $this->form->getTable();
                $saveInvoice["approval_type"]   = $this->form->approvalType ?? "";
                $saveInvoice["approval_set_id"] = $this->form->getApprovalSetId() ?? 0;
                $saveInvoice["pk_id"]           = $pkId;
                $saveInvoice["field_key"]       = $fieldKey;
                foreach ($invoices as $invoice) {
                    $saveInvoice["invoice_type"]   = $invoice["invoice_type"] ?? "";
                    $saveInvoice["invoice_number"] = $invoice["invoice_number"] ?? "";
                    $saveInvoice["invoice_code"]   = $invoice["invoice_code"] ?? "";
                    $saveInvoices[]                = $saveInvoice;
                }
            }
            CustomFieldInvoice::query()
                ->where(["table" => $this->form->getTable(), "pk_id" => $pkId])
                ->update(delDateTime());
            if (!empty($saveInvoices)) {
                if (!CustomFieldInvoice::query()->insert($saveInvoices)) _throwException("发票保存出错");
            }
        }
    }

    /**
     * Notes:审批取消等作废操作-则将存入的发票记录进行删除
     * Date: 2024/12/19
     * @param $pkId
     */
    public function invoiceDel($pkId): void
    {
        CustomFieldInvoice::invoiceDel($pkId, $this->form->getTable());
        return;
    }

    /**
     * Notes:审批使用的控件组插入
     * Date: 2025/2/25
     * @param $approvalId
     */
    public function approvalRelateControl($approvalId): void
    {
        if ($control = $this->form->eventControl) {
            $insertControls = [];
            $insertControl  = ["company_id" => $this->form->companyId, "approval_id" => $approvalId];
            foreach ($control as $v) {
                $insertControl["controls"] = $v["event_control"];
                $insertControls[]          = $insertControl;
            }
            ApprovalRelateControl::query()->insert($insertControls);
        }
        return;
    }

}
