<?php

namespace lanerp\dong\Models;

use App\Helpers\Arrs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * This is the model class for table "custom_field_sorts".
 *
 * @property   int    $id
 * @property   int    $company_id  公司 id
 * @property   string $field_form  字段所在表
 * @property   int    $field_id    字段id
 * @property   int    $sort        排序
 */
class CustomFieldSort extends Model
{

    protected $table = 'custom_field_sorts';

    protected $fillable = ['company_id', 'field_form', 'field_id', 'sort'];

    protected $hidden = [];

    /**
     * Notes:获取字段
     * @param string $type   default
     * @param string $prefix 前缀
     * @return array
     */
    public static function columns(string $type = "", string $prefix = "", $pkId = null): array
    {
        $pkId    = $pkId ?: 'id as field_sort_id';
        $columns = [
            "default" => [$pkId, 'company_id', 'field_form', 'field_id', 'sort'],
        ];
        $columns = $columns[$type] ?? $columns["default"];
        return Arrs::unshiftPrefix($columns, $prefix);
    }


}
