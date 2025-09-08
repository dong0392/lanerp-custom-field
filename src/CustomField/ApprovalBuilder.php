<?php

namespace lanerp\dong\CustomField;

// use App\Modules\Approval\Enums\ApprovalType;
// use lanerp\dong\CustomField\FieldValueMerge;
// use App\Models\Approval;
// use App\Models\ApprovalSet;
// use App\Models\ApprovalSetMore;

//use App\Modules\Approval\Services\ContentService;
//use App\Modules\Approval\Services\FlowService;
//use App\Modules\Approval\Services\FormService;
//use App\Modules\Approval\Services\MainService;
use Illuminate\Support\Facades\Http;
use lanerp\dong\CustomField\FieldValueMerge;
use Sajya\Client\Client;

class ApprovalBuilder
{

    // ============================ 独立实例属性 ===========================
    //public ApprovalSet      $approvalSet; // 审批设置对象（init时传入，核心依赖）
    public int    $userId; // 申请人ID
    public int    $companyId; // 公司ID
    public int    $approvalSetId; //
    public string $approvalType; //
    //public ?ApprovalSetMore $approvalSetMore      = null; // 审批设置扩展信息（自动加载）
    public array  $formParams     = []; // 表单参数
    public array  $contentParams  = []; // 审批内容参数
    public array  $flowParams     = []; // 审批流参数
    public array  $businessRelate = []; // 业务关联参数
    public ?array $overviewFields = null; // 概览字段
    //public ?array           $overviewFieldsConfig = []; // 概览设置字段
    public ?Form $form; // 表单对象


    // ============================ 构造函数 ===========================
    public function __construct(
        //private MainService $mainService, // 审批主表
        //private FormService $formService, // 审批的 form_info 表处理
        //private ContentService $contentService, // 审批内容处理
        // private OverViewService $overviewService, // 审批概览处理
        //private FlowService $flowService, // 审批流数据处理
    )
    {
    }

    // ============================ 主入口 ===========================

    /**
     * 初始化审批流程构建器
     * @param int $userId 申请人ID
     * @param int $companyId 公司ID
     * @param string $approvalType
     * @param int $approvalSetId
     * @param Form|null $form 表单对象（可选，默认使用构造函数注入的form）
     * @return self
     */
    public static function init(int $userId, int $companyId, string $approvalType = '', int $approvalSetId = 0, ?Form $form = null): self
    {
        if ($approvalType === '' && $approvalSetId === 0) {
            _throwException('审批类型或审批设置ID不能为空');
        }
        // 实例化Builder
        $instance = app(self::class);
        // 保存核心参数（直接从$approvalSet提取，无需单独传approvalSetId）
        $instance->approvalSetId = $approvalSetId;
        $instance->approvalType  = $approvalType;
        $instance->userId        = $userId;
        $instance->companyId     = $companyId;
        $instance->form          = $form ?? Form::init(uid: $userId, companyId: $companyId);

        return $instance;
    }

    // ============================ 各个业务处理方法 ============================

    /**
     * 设置审批form_info表数据
     */
    public function setFormInfo(?array $formInfo = null): self
    {
        $this->formParams = $formInfo ?? $this->form->request->except(['node_list', 's']);
        return $this;
    }

    /**
     * 补充其他form_info字段
     * @param array $value
     * @return $this
     */
    public function addFormInfo(array $value): self
    {
        $this->formParams = array_merge($this->formParams, $value);
        return $this;
    }

    /**
     * 设置审批内容
     */
    public function setApprovalContent(bool $isGenCustomDetails = false, ?array $content = null): self
    {
        $this->contentParams['custom_details'] = $isGenCustomDetails
            ? FieldValueMerge::get($this->form->formFields, $this->formParams) // 生成自定义字段
            : ($content ?? $this->formParams); // 直接使用传入内容或formParams
        return $this;
    }

    /**
     * 补充审批内容业务字段
     * @param array $businessData
     * @return $this
     */
    public function setApprovalContentBusinessData(array $businessData): self
    {
        $this->contentParams['business_data'] = $businessData;
        return $this;
    }

    /**
     * 设置业务关联（直接使用关联类型和ID）
     */
    public function setRelate(string $relateType, int $relateId): self
    {
        $this->businessRelate = compact('relateType', 'relateId'); // 统一使用关联数组
        return $this;
    }

    /**
     * 设置审批流节点
     */
    public function setFlow(array $nodes): self
    {
        $this->flowParams = $nodes;
        return $this;
    }

    /**
     * 设置概览字段
     */
    public function setOverview(?array $overview = null): self
    {
        $this->overviewFields = $overview;
        return $this;
    }


    // ============================ 执行方法 ============================

    /**
     * 执行审批创建流程，返回创建的核心业务数据
     * @return array 包含审批主表、表单、内容、流程节点等数据
     */
    public function execute()
    {
        if (empty($this->overviewFields)) {
            _throwException('审批流不能为空');
        }
        $mainParams = [
            'user_id'         => $this->userId,
            'company_id'      => $this->companyId,
            'approval_set_id' => $this->approvalSetId,
            'approval_type'   => $this->approvalType,
            'overview_fields' => $this->overviewFields ?? [], // 概览字段
        ];
        $response   = (new Client(
            Http::baseUrl(config('app.domain_url') . "/v1/oa")->withHeader('uid', $this->userId)
        ))
            ->execute('oa@approvalCreate', [
                'main'      => $mainParams,    // 审批主表模型（含 id、创建时间等核心字段）
                'relate'    => $this->businessRelate,
                'form'      => $this->formParams,        // 表单存储结果（如 form_info 表数据）
                'content'   => $this->contentParams,     // 审批内容数据（如 custom_details、business_data）
                'flowNodes' => $this->flowParams ?? [],   // 流程节点数据（如节点ID、审批人、顺序等）
            ]);
        $result     = $response->result();
        if (empty($result) || (isset($result['original']) && $result['original']['code'] !== 200)) {
            _throwException('审批创建失败');
        }

        return $result;
    }
}