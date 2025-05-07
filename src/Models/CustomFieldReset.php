<?php

namespace App\Models;

use App\Helpers\Arrs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use function Symfony\Component\String\s;

/**
 * This is the model class for table "custom_field_resets".
 *
 * @property   int    $field_id    字段id
 * @property   int    $company_id  公司 id
 * @property   string $field_form  字段所在表
 * @property   string $field_name  字段名
 * @property   int    $is_required 数据是否必填 0=否 1=是
 * @property   int    $status      是否禁用 0=否 1=是
 * @property   string $extend      字段扩展信息
 */
class CustomFieldReset extends Model
{

    protected $table = 'custom_field_resets';

    protected $fillable = ['field_id', 'company_id', 'field_form', 'field_name', 'is_required', 'status', 'extend'];

    protected $hidden = [];

    protected $casts = [
        'extend' => 'json'
    ];

    // 设置主键是否自增
    public $incrementing = false;

    /**
     * Notes:获取字段
     * @param string $type   default
     * @param string $prefix 前缀
     * @return array
     */
    public static function columns(string $type = "", string $prefix = ""): array
    {
        $columns = [
            "default" => ['field_id', 'field_form', 'field_name', 'is_required', 'status', 'extend'],
        ];
        $columns = $columns[$type] ?? $columns["default"];
        return Arrs::unshiftPrefix($columns, $prefix);
    }


    /**
     * Notes:表单字段重置
     * Date: 2024/10/25
     * @param      $fields
     * @param null $companyId
     * @return mixed
     */
    public static function fieldsReset($fields, $companyId = null)
    {
        $resetFieldIds = $fields->filter(fn($field) => $field['is_reset'] === 1)->pluck('field_id')->all();
        if ($resetFieldIds) {
            $resetFields = static::query()->select(static::columns())
                ->where(["company_id" => $companyId ?? authUser()->company_id])->whereIn("field_id", $resetFieldIds)
                ->get()->keyBy("field_id");
            //dd($resetFields);
            if ($resetFields) {
                //可加&可不加&
                foreach ($fields as &$v) {
                    if ($resetField = $resetFields->get($v->field_id)) {
                        //dd($v->field_name,$resetField->field_name);
                        $v->field_name  = $resetField->field_name;
                        $v->is_required = $resetField->is_required;
                        $v->status      = $resetField->status;
                        $v->extend      = $resetField->extend;
                    }
                }
            }
        }
        return $fields;
    }


}
