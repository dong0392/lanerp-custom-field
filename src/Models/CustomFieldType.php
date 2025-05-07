<?php

namespace App\Models;

use App\Helpers\Arrs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * This is the model class for table "custom_field_types".
 *
 * @property   int    $id
 * @property   string $name       字段类型名称
 * @property   string $type       字段类型
 * @property   string $icon       图标
 * @property   string $extend     字段扩展信息
 * @property   string $group      字段类型组
 * @property   int    $sort       排序(用于系统字段)
 * @property   string $tag        字段类型标签
 * @property   int    $is_popular 热门控件 0否 1是
 * @property   string $deleted_at
 * @property   string $created_at
 * @property   string $updated_at
 */
class CustomFieldType extends Model
{

    protected $table = 'custom_field_types';

    protected $fillable = ['field_name', 'field_type', 'icon', 'extend', 'group', 'sort', 'tag', 'is_popular'];

    protected $hidden = [];

    public const TAG_CONTROL    = "control";
    public const TAG_CONTROLS   = "controls";
    public const TAG_FORM_GROUP = "form_group";
    public const TAG_FORM_PK    = "form_pk";
    public const TAG_FORM       = "form";

    public const CONTROL_GROUP_TEXT_NUMERIC     = "text_numeric";
    public const CONTROL_GROUP_OPTION           = "option";
    public const CONTROL_GROUP_DATE             = "date";
    public const CONTROL_GROUP_LOCATION         = "location";
    public const CONTROL_GROUP_SYSTEM_CONTROL   = "system_control";
    public const CONTROL_GROUP_OTHER            = "other";
    public const CONTROL_GROUP_ENHANCED_CONTROL = "enhanced_control";
    public const CONTROL_GROUP                  = [
        self::CONTROL_GROUP_TEXT_NUMERIC     => ['title' => '文本/数值', 'value' => self::CONTROL_GROUP_TEXT_NUMERIC],
        self::CONTROL_GROUP_OPTION           => ['title' => '选项', 'value' => self::CONTROL_GROUP_OPTION],
        self::CONTROL_GROUP_DATE             => ['title' => '日期', 'value' => self::CONTROL_GROUP_DATE],
        self::CONTROL_GROUP_LOCATION         => ['title' => '地点', 'value' => self::CONTROL_GROUP_LOCATION],
        self::CONTROL_GROUP_SYSTEM_CONTROL   => ['title' => '系统控件', 'value' => self::CONTROL_GROUP_SYSTEM_CONTROL],
        self::CONTROL_GROUP_OTHER            => ['title' => '其他', 'value' => self::CONTROL_GROUP_OTHER],
        self::CONTROL_GROUP_ENHANCED_CONTROL => ['title' => '增强控件', 'value' => self::CONTROL_GROUP_ENHANCED_CONTROL],
    ];

    public const CONTROLS_GROUP_ATTENDANCE = "attendance";
    public const CONTROLS_GROUP_FINANCIAL  = "financial";
    public const CONTROLS_GROUP_OTHER      = "other";
    public const CONTROLS_GROUP            = [
        self::CONTROLS_GROUP_ATTENDANCE => ['title' => '考勤', 'value' => self::CONTROLS_GROUP_ATTENDANCE],
        self::CONTROLS_GROUP_FINANCIAL  => ['title' => '财务', 'value' => self::CONTROLS_GROUP_FINANCIAL],
        //self::CONTROLS_GROUP_OTHER      => ['title' => '其他', 'value' => self::CONTROLS_GROUP_OTHER],//2.1再加
    ];

    protected static function booted()
    {
        parent::booted();
        static::addGlobalScope(new \lanerp\dong\Models\Scopes\SoftDeleteScope);
    }

    /**
     * Notes:
     * Date: 2024/10/23
     * @param string $type default|basic
     * @return array
     */
    public static function columns(string $type = ""): array
    {
        $columns = [
            "default" => ['field_name', 'field_type', 'icon', 'extend', 'group', 'tag', 'is_popular'],
            "basic"   => ['field_name', 'field_type', 'icon', 'extend'],
        ];
        return $columns[$type] ?? $columns["default"];
    }

    /**
     * Notes:自定义字段类型
     * Date: 2024/10/21
     * @return \Illuminate\Database\Eloquent\Collection'
     */
    public static function getTypes()
    {
        /*$where[]      = [
            function ($query) {
                $query->whereNull("deleted_at");
            }];*/
        $where[] = [
            function ($query) {
                $query->whereIn("tag", [static::TAG_CONTROL, static::TAG_CONTROLS]);
            }];
        return static::query()->select(static::columns())->where($where)->orderBy("sort")->get()->transform(function ($type) {
            $type->extend = json_decode($type->extend, true) ?? (object)[];
            return $type;
        });
    }

    /**
     * Notes:获取控件组字段
     * Date: 2024/10/28
     * @param $controls
     * @return array
     */
    public static function getControlsField($controls)
    {
        $where['field_form'] = $controls;
        $where['company_id'] = 0;
        $where['is_system']  = 0;
        $fields              = CustomField::query()->select(array_merge(CustomField::columns(), ['sort']))
            ->where($where)->orderByRaw("sort asc,id asc")->get();
            //->transform(function ($row) {
            //    $row->extend = json_decode($row->extend, true) ?? (object)[];
            //    $row->rules  = json_decode($row->rules, true) ?? [];
            //    return $row;
            //});
        return Arrs::listToTree($fields->toArray(), 'field_key', 'p_field_key', '_child', '', true);
    }

}
