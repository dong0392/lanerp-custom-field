<?php

namespace App\Models;

use App\Helpers\Arrs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * This is the model class for table "custom_field_sn_incs".
 *
 * @property   int    $id
 * @property   int    $company_id  公司 id
 * @property   string $field_form  字段所在表
 * @property   string $field_key   字段key
 * @property   int    $inc_num     自增序号
 * @property   string $inc_date    自增日期
 */
class CustomFieldSnInc extends Model
{

    protected $table = 'custom_field_sn_incs';

    protected $fillable = ['id', 'company_id', 'field_form', 'field_key', 'inc_num','inc_date'];

    protected $hidden = [];

    protected $casts = [];

    public $timestamps = false;  // 禁用时间戳功能

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
        $pkId    = $pkId ?: 'id as custom_field_sn_inc_id';
        $columns = [
            "default" => [$pkId, 'id', 'company_id', 'field_form', 'field_key', 'inc_num','inc_date'],
        ];
        $columns = $columns[$type] ?? $columns["default"];
        return Arrs::unshiftPrefix($columns, $prefix);
    }

}
