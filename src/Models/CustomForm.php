<?php

namespace App\Models;

use App\Helpers\CustomField\Form;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * This is the model class for table "custom_forms".
 *
 * @property   int    $id
 * @property   string $form       表单
 * @property   string $form_name  表单名称
 * @property   string $pk         表单主键
 * @property   string $icon       图标
 * @property   int    $is_quote   是否可以引用表单字段 0=否 1=是
 * @property   string $table      源于数据表名
 * @property   string $model      表单模型
 * @property   int    $sort       排序
 * @property   string $deleted_at
 * @property   string $created_at
 * @property   string $updated_at
 */
class CustomForm extends Model
{

    protected $table = 'custom_forms';

    protected $fillable = ['form', 'form_name', 'pk', 'icon', 'is_quote', 'table', 'model'];

    protected $hidden = [];

    public const APPROVAL            = "approval";
    public const USERS               = "users";
    public const CUSTOMERS           = "customers";
    public const CUSTOMER_CONTACTS   = "customer_contacts";
    public const CUSTOMER_FOLLOW_UPS = "customer_follow_ups";
    public const CUSTOMER_MANAGES    = "customer_manages";
    public const PROJECTS            = "projects";
    public const PROJECT_STAGE_TASKS = "project_stage_tasks";
    public const PROJECT_QUOTATIONS  = "project_quotations";
    public const ORDERS              = "orders";
    public const PURCHASES           = "purchases";
    public const WORKORDERS          = "workorders";

    protected static function booted()
    {
        parent::booted();
        static::addGlobalScope(new \lanerp\dong\Models\Scopes\SoftDeleteScope);
    }

    /**
     * Notes:获取字段
     * @param string $type default
     * @return array
     */
    public static function columns(string $type = ""): array
    {
        $columns = [
            "default" => ['form', 'form_name', 'pk', 'icon', 'is_quote', 'table', 'model'],
        ];
        return $columns[$type] ?? $columns["default"];
    }

    /**
     * Notes:获取所有表单
     * Date: 2024/10/23
     * @return \Illuminate\Database\Eloquent\Collection|mixed
     */
    public static function getForms(): mixed
    {
        static $forms;
        if (!isset($forms)) {
            $forms = static::query()->select(static::columns())->orderBy("sort")->get();
        }
        return $forms;
    }

    /**
     * Notes:获取可以关联的表单
     * Date: 2024/10/23
     * @return \Illuminate\Support\Collection
     */
    public static function getQuoteForms(): \Illuminate\Support\Collection
    {
        return static::getForms()->where('is_quote', 1)->values();
    }

    /**
     * Notes:自定义表单转真实数据表
     * Date: 2024/10/29
     * @param $form
     * @return mixed
     */
    public static function getFormInfo($form): mixed
    {
        return static::query()->where('form', $form)->first(["table", "model", "pk"]);
    }

    public static function getRequestInfo($table, $pkId, $field = "request_info"): mixed
    {
        static $formInfos;
        $key = $table . ':' . $pkId;
        if (isset($formInfos[$key])) {
            $formInfo = $formInfos[$key];
        } else {
            if ($table === static::APPROVAL) {
                $formInfo = ApprovalFormInfo::query()->where(['approval_id' => $pkId])
                    ->first(['approval_id', 'form_info as request_info', 'event_control', 'is_update', 'created_at'])
                    ?->toArray();
            } else {
                $formInfo["request_info"]  = request()->all();
                $formInfo["event_control"] = Form::init()->eventControl;
                $formInfo["created_at"]    = date("Y-m-d H:i:s");
            }
            $formInfos[$key] = $formInfo;
        }

        if ($field !== null) {
            $formInfo = $formInfo[$field] ?? null;
        }

        return $formInfo;
    }
}
