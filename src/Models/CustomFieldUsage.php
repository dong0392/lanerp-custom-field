<?php

namespace lanerp\dong\Models;

use lanerp\common\Helpers\Arrs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * This is the model class for table "custom_field_usages".
 *
 * @property   int    $id
 * @property   int    $company_id   公司 id
 * @property   string $usage_table  使用表
 * @property   int    $pk_id        使用表主键id
 * @property   string $field_form   字段所在表
 * @property   string $field_keys   字段key
 */
class CustomFieldUsage extends Model
{

    protected $table = 'custom_field_usages';

    protected $fillable = ['id', 'company_id', 'usage_table', 'pk_id', 'field_form', 'field_keys'];

    protected $hidden = [];

    protected $casts = [];

    public $timestamps = false;

    public const TABLE_PROJECT_GROUP = "project_groups";

    public const TABLE_WORKORDER_SET = "workorder_set";


    protected static function booted()
    {
        parent::booted();
        static::addGlobalScope(new \lanerp\dong\Models\Scopes\WithCompanyIdScope);
    }

    /**
     * 作用域：移除 company_id 条件
     * 当需要查询不带 company_id 的用户时，调用此作用域
     */
    public function scopeWithoutCompanyId(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        // 清除全局作用域的 `company_id` 条件
        return $query->withoutGlobalScope(\lanerp\dong\Models\Scopes\WithCompanyIdScope::class);
    }


    /**
     * Notes:获取字段
     * @param string $type   default
     * @param string $prefix 前缀
     * @return array
     */
    public static function columns(string $type = "", string $prefix = "", $pkId = null): array
    {
        $pkId    = $pkId ?: 'id as custom_field_usage_id';
        $columns = [
            "default" => [$pkId, 'company_id', 'usage_table', 'pk_id', 'field_form', 'field_keys'],
        ];
        $columns = $columns[$type] ?? $columns["default"];
        return Arrs::unshiftPrefix($columns, $prefix);
    }

    /**
     * Notes:字段使用情况记录
     * Date: 2024/12/9
     * @param $usageTable
     * @param $pkId
     * @param $fieldForm
     * @param $fieldKeys
     */
    public static function record($usageTable, $pkId, $fieldForm, $fieldKeys)
    {
        //多个类型$fieldKeys，可以先合并，再往过传
        $fieldKeys = collect($fieldKeys)->filter()->pluck('field_key')->unique()->implode(',');
        $fieldKeys && static::query()->updateOrInsert(
            ['company_id' => user()->company_id, 'usage_table' => $usageTable, 'pk_id' => $pkId, 'field_form' => $fieldForm],
            ['field_keys' => $fieldKeys]
        );
        return;
    }

    /**
     * Notes:字段使用情况记录根据业务去删除
     * Date: 2024/12/9
     * @param $usageTable
     * @param $pkId
     * @return mixed
     */
    public static function del($usageTable, $pkId)
    {
        return static::query()
            ->where(['company_id' => user()->company_id, 'usage_table' => $usageTable, 'pk_id' => $pkId])
            ->delete();
    }

    /**
     * Notes:验证自定义字段是否在其它地方使用
     * Date: 2024/12/9
     * @param $fieldForm
     * @param $fieldKey
     * @return bool
     */
    public static function isUsage($fieldForm, $fieldKey): bool
    {
        return static::query()->where("field_form", $fieldForm)
            ->whereRaw("FIND_IN_SET('{$fieldKey}', field_keys)")
            ->exists();
    }
}
