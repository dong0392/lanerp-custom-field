<?php

namespace lanerp\dong\Models;

use App\Helpers\Arrs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * This is the model class for table "custom_field_form_snapshots".
 *
 * @property   int    $id
 * @property   int    $company_id          公司 id
 * @property   string $form_snapshot_key   表单生成的key
 * @property   string $form                表单
 * @property   string $form_fields         表单字段
 */
class CustomFieldFormSnapshot extends Model
{

    protected $table = 'custom_field_form_snapshots';

    protected $fillable = ['company_id', 'form_snapshot_key', 'form', 'form_fields'];

    protected $hidden = [];

    protected $casts = [
        'form_fields' => 'json',
    ];

    public $timestamps = false;


    /**
     * Notes:获取字段
     * @param string $type   default
     * @param string $prefix 前缀
     * @return array
     */
    public static function columns(string $type = "", string $prefix = "", $pkId = null): array
    {
        $pkId    = $pkId ?: 'id as custom_field_form_snapshot_id';
        $columns = [
            "default" => [$pkId, 'company_id', 'form_snapshot_key', 'form', 'form_fields'],
        ];
        $columns = $columns[$type] ?? $columns["default"];
        return Arrs::unshiftPrefix($columns, $prefix);
    }

    public static function getFields($formSnapshotKey, $form = null): \Illuminate\Support\Collection
    {
        $where["form_snapshot_key"] = $formSnapshotKey;
        $form && $where["form"] = $form;
        return collect(static::query()->where($where)->value("form_fields"));
    }

    private static function filterFieldTypes(\Illuminate\Support\Collection $fields, $filterFieldTypes): \Illuminate\Support\Collection
    {
        $filterFieldKeys = $fields->filter(fn($field) => in_array($field['field_type'], $filterFieldTypes, true))->pluck('field_key')->all();
        if ($filterFieldKeys) {
            //可加&可不加&
            foreach ($fields as $k => $v) {
                if (in_array($v["field_key"], $filterFieldKeys, true)) {
                    $fields->forget($k);// 从 $fields 中删除 $v
                }
                if (in_array($v["p_field_key"], $filterFieldKeys, true)) {
                    $v["p_field_key"] = "";
                }

            }
        }
        return $fields->values();
    }

    public static function getFormFields($formSnapshotKey, $form = null): array
    {
        return Arrs::listToTree(
            static::filterFieldTypes(static::getFields($formSnapshotKey, $form), ["system"])->toArray(),
            'field_key',
            'p_field_key',
            '_child',
            '',
            true
        );
    }
}
