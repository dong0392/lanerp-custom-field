<?php

namespace lanerp\dong\Models;

use lanerp\common\Helpers\Arrs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * This is the model class for table "custom_field_quote_forms".
 *
 * @property   int    $id
 * @property   int    $company_id        公司 id
 * @property   string $field_form        字段所在表
 * @property   string $quote_field_form  引用字段表
 * @property   string $unique_field_key  唯一标识控件字段key
 * @property   int    $is_add            是否可以新增数据 0=否 1=是
 * @property   int    $is_edit           是否可以编辑引用表单数据 0=否 1=是
 * @property   string $pk                表单主键
 */
class CustomFieldQuoteForm extends Model
{

    protected $table = 'custom_field_quote_forms';

    protected $fillable = ['id', 'company_id', 'field_form', 'quote_field_form', 'unique_field_key', 'is_add', 'is_edit', 'pk'];

    protected $hidden = [];

    /**
     * Notes:获取字段
     * @param string $type   default
     * @param string $prefix 前缀
     * @return array
     */
    public static function columns(string $type = "", string $prefix = "", $pkId = null): array
    {
        $pkId    = $pkId ?: 'id as field_quote_form_id';
        $columns = [
            "default" => [$pkId, 'company_id', 'field_form', 'quote_field_form', 'unique_field_key', 'is_add', 'is_edit', 'pk'],
        ];
        $columns = $columns[$type] ?? $columns["default"];
        return Arrs::unshiftPrefix($columns, $prefix);
    }



}
