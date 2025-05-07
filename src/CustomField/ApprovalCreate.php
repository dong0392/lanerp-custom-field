<?php

namespace lanerp\common\Helpers\CustomField;

use App\Models\Approval;
use App\Models\ApprovalFormInfo;
use App\Models\ApprovalSet;
use App\Models\CustomForm;
use App\Services\Api\Approval\ApprovalCreateService;
use App\Services\Api\Approval\ApprovalNodeService;

class ApprovalCreate
{

    protected static $instances;
    protected Form   $form;
    public object    $user;
    private          $companyId;
    private          $uid;
    private          $approvalType;
    private          $approvalNode;
    private          $formInfo;
    private          $approvalContent;
    public           $approvalId;
    private          $approval;
    private          $relateType = "";
    private          $relateId   = 0;
    private          $overview   = [];

    public function __construct($approvalType, ?Form $form = null, object $user = null)
    {
        $this->form         = $form ?? Form::init();
        $this->user         = $user ?? authUser();
        $this->uid          = $this->user->id;
        $this->companyId    = $this->user->company_id;
        $this->approvalType = $approvalType;
        if (empty($this->form->request->input("node_list"))) _throwException('请联系管理员设置审批流程');
        $this->approvalNode = $this->form->request->node_list;
    }

    public static function init($approvalType, ?Form $form = null, object $user = null)
    {
        return static::$instances ?? (static::$instances = new static($approvalType, $form, $user));
    }

    public function setFormValue($isGenCustomDetails = false, $formInfo = null)
    {
        $this->formInfo = $formInfo ?? $this->form->request->except(["node_list", "s"]);
        if ($isGenCustomDetails) {
            $this->approvalContent["custom_details"] = FieldValueMerge::get($this->form->formFields, $this->formInfo);
        }
        return $this;
    }

    public function addFormValue(array $value)
    {
        $this->formInfo = array_merge($this->formInfo, $value);
        return $this;
    }

    public function getFormValue()
    {
        return $this->formInfo;
    }

    public function setContent($content = null, $contentKey = "custom_details")
    {
        $this->approvalContent[$contentKey] = $content ?? $this->formInfo;
        return $this;
    }

    public function setBusinessData($businessData)
    {
        $this->approvalContent["business_data"] = $businessData;
        return $this;
    }

    public function setRelate($relateType, $relateId)
    {
        $this->relateType = $relateType;
        $this->relateId   = $relateId;
        return $this;
    }

    public function setOverview($overview)
    {
        $this->overview = $overview;
        return $this;
    }

    public function execute($isExecuteCustomFieldLogic = null)
    {
        $approvalCreate            = (new ApprovalCreateService());
        $approval                  = $approvalCreate->businessCreate($this->user, $this->approvalType, $this->approvalContent, $this->relateType, $this->relateId, $this->overview);
        $this->groupNodes          = $approvalCreate->createNodeInfo($this->user, $this->approvalNode, $approval);
        $this->approvalId          = $approval->id;
        $this->approval            = $approval;
        $isExecuteCustomFieldLogic = $isExecuteCustomFieldLogic ?? isset($this->approvalContent["custom_details"]);
        //dd($this->formInfo);
        if ($isExecuteCustomFieldLogic) {
            Form::init()->setTable(CustomForm::APPROVAL)->setApprovalType($approval->approval_type)->setApprovalSetId($approval->approval_set_id)
                ->approvalSave($approval->id, $this->formInfo)->frontControlEventTrigger($approval->id);
        } else {
            $this->formInfo && ApprovalFormInfo::create(["company_id" => $this->companyId, "approval_id" => $approval->id, "form_info" => $this->formInfo]);
        }
        return $this;
    }

    /**
     * Notes:自定义字段审批添加节点
     * Date: 2024/12/19
     * @return $this
     */
    public function handleProcess(): self
    {
        // 处理审批流程各节点业务逻辑
        ApprovalNodeService::handleApprovalProcess($this->user, $this->groupNodes);
        return $this;
    }

}
