<?php

namespace lanerp\common\Helpers\CustomField;


class OrderBuider
{
    private static OrderBuider $instances;
    private string             $as;
    private string             $extends;

    private array $order = [];

    private array $fieldType = [];

    public function __construct(?string $as)
    {
        $this->as      = $as ? $as . "." : "";
        $this->extends = $this->as . "extends";
    }

    public static function init($as = null)
    {
        return static::$instances ?? (static::$instances = new static($as));
    }

    //排序后追加order
    public function pushOrder(array|string $order): OrderBuider
    {
        if (!$order) return $this;
        $order = is_array($order) ? $order : [$order];
        array_push($this->order, ...$order);
        return $this;
    }

    //最前面插入order
    public function unshiftOrder(array|string $order): OrderBuider
    {
        if (!$order) return $this;
        $order = is_array($order) ? $order : [$order];
        array_unshift($this->order, ...$order);
        return $this;
    }

    public function get(): ?string
    {
        return $this->order ? implode(",", $this->order) : null;
    }

    /*"custom_sort": [
        {
            "is_system": true,
            "field_type": "money",
            "field_key": "field_2505",
            "direction": "asc"
        },
        {
            "is_system": false,
            "field_type": "money",
            "field_key": "field_2505",
            "direction": "asc"
        }
    ]*/
    public function byCustomSort($customSort = null): OrderBuider
    {
        $customSort = $customSort ?? request()->input("custom_sort", []);
        foreach ($customSort as $column) {
            if ($column["is_system"]) {
                $order = "{$column["field_key"]} {$column["direction"]}";
            } else {
                $type  = $this->fieldType[$column["field_type"]] ?? "default";
                $order = $this->$type($column["field_key"], $column["direction"]);
            }
            $this->order[] = $order;
        }
        return $this;
    }

    public function byPkSort($pkSort = null): OrderBuider
    {
        $pkSort = $pkSort ?? request()->input("pk_sort", []);
        foreach ($pkSort as $pk => $ids) {
            if ($ids) {
                $ids           = implode(",", $ids);
                $this->order[] = "FIELD({$this->as}`{$pk}`,$ids) DESC";
            }
        }
        return $this;
    }


    public function default($fieldKey, $direction)
    {
        return "json_unquote(json_extract({$this->extends}, '$.{$fieldKey}.value')) {$direction}";
    }

}
