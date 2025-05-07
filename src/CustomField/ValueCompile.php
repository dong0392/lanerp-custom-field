<?php

namespace lanerp\dong\CustomField;

use lanerp\common\Helpers\Strs;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Department;
use App\Models\Order;
use App\Models\Project;
use App\Models\ProjectQuotation;
use App\Models\ProjectStage;
use App\Models\User;
use App\Models\UserTag;
use App\Models\WorkOrderType;

class ValueCompile
{
    public const wayRelated       = 1;
    public const wayHasOne        = 2;
    public const wayHasMany       = 3;
    public const wayBelongsToMany = 4;

    public static function userTagQuery($ids): \Illuminate\Database\Eloquent\Collection
    {
        return UserTag::query()->whereIn("id", $ids)->get(["id", "name"]);
    }

    public static function contactQuery($ids): \Illuminate\Database\Eloquent\Collection
    {
        return User::query()->whereIn("id", $ids)->get(["id", "name", "avatar"]);
    }

    public static function customerContactQuery($ids): \Illuminate\Database\Eloquent\Collection
    {
        return CustomerContact::query()->whereIn("id", $ids)->get(["id", "name", "phone"]);
    }

    public static function projectQuotationQuery($ids): \Illuminate\Database\Eloquent\Collection
    {
        return ProjectQuotation::query()->from("project_quotations", "p")
            ->leftJoin("users as u", fn($join) => $join->on('u.id', '=', 'p.quoter'))//->whereNull('u.deleted_at')
            ->whereIn("id", $ids)->get(["p.id", "p.quotation_num", "u.name as quoter_name", "p.quotation_at"]);
    }

    public static function departmentQuery($ids, $way = ""): \Illuminate\Support\Collection
    {
        $departmentsIndex = Department::getDepartments();
        $data             = [];
        if ($way === self::wayHasOne) {
            foreach ($ids as $id) {
                $departmentsIndex[$id] && $data[] = $departmentsIndex[$id];
            }
        } else {
            $data = $departmentsIndex;
        }
        return collect($data);
    }

    public static function customerQuery($ids)
    {
        $collect = Customer::query();
        if (is_array($ids)) {
            $collect = $collect->whereIn("id", $ids)->get(["id", "name"]);
        } else {
            $collect = $collect->where("id", $ids)->first(["id", "name"]);
        }
        return $collect;
    }

    public static function projectQuery($ids)
    {
        $collect = Project::query();
        if (is_array($ids)) {
            $collect = $collect->whereIn("id", $ids)->get(["id", "name"]);
        } else {
            $collect = $collect->where("id", $ids)->first(["id", "name"]);
        }
        return $collect;
    }

    public static function orderQuery($ids)
    {
        $collect = Order::query();
        if (is_array($ids)) {
            $collect = $collect->whereIn("id", $ids)->get(["id", "order_num as name"]);
        } else {
            $collect = $collect->where("id", $ids)->first(["id", "order_num as name"]);
        }
        return $collect;
    }

    public static function workorderTypeQuery($ids)
    {
        $collect = WorkOrderType::query();
        if (is_array($ids)) {
            $collect = $collect->whereIn("id", $ids)->whereNull("deleted_at")->get(["id", "name"]);
        } else {
            $collect = $collect->where("id", $ids)->whereNull("deleted_at")->first(["id", "name"]);
        }
        return $collect;
    }

    public static function projectStageQuery($ids)
    {
        $collect = ProjectStage::query();
        if (is_array($ids)) {
            $collect = $collect->whereIn("id", $ids)->get(["id", "name"])->push(...ProjectStage::otherStage());
        } else {
            if (isset(ProjectStage::PROJECT_STAGE[$ids])) {
                $collect = collect(collect(ProjectStage::otherStage())->keyBy("id")->get($ids));
            } else {
                $collect = $collect->where("id", $ids)->first(["id", "name"]);
            }
        }
        return $collect;
    }

    public static function baseWayArr($query, $value, $way, $columns, $keyBy = "id")
    {
        $data = $text = [];
        if ($way === self::wayHasOne) {//方式一详情
            //$value = [747,1,2]或747,1,2
            //!is_array($value) && $value = explode(',', $value);
            $value = Strs::explodeToInt($value);
            $value && $text = static::$query($value, $way)->toArray();
            $data = ["value" => $value, "text" => $text];
        } elseif ($way === self::wayBelongsToMany) {//方式二 列表不带值
            //$value  = ["10" => [747, 1, 2], "11" => [747, 1, 2]];
            //$value    = array_map(static fn($v) => is_array($v) ? $v : explode(',', $v), $value);
            $value    = array_map(static fn($v) => Strs::explodeToInt($v), $value);
            $queryIds = array_unique(array_merge(...$value));
            if ($queryIds) {
                $res = static::$query($queryIds)->keyBy($keyBy)->toArray();
                foreach ($value as $id => $valQueryIds) {
                    $text = [];
                    foreach ($valQueryIds as $queryId) {
                        isset($res[$queryId]) && $text[] = $res[$queryId];
                    }
                    $data[$id] = ["value" => array_filter($valQueryIds), "text" => $text];
                }
            }
        } elseif ($way === self::wayHasMany) {
            //例：
            //$columns = ["id" => "contact_id", "name" => "contact_name"];
            //$value   = [["contact_id" => 1, "contact_name" => "联系人姓名"]];
            $ids = $texts = [];
            foreach ($value as $row) {
                $id = 0;
                foreach ($columns as $k => $column) {
                    $id === 0 && $id = $row[$column] ?? null;
                    $text[$k] = $row[$column] ?? null;
                }
                $texts[] = $text;
                $ids[]   = $id;
            }
            $data = ["value" => $ids, "text" => $texts];
            //dd($data);
        }
        return $data;

    }

    //编译公司用户标签
    public static function checkboxUserTag($value, $way = self::wayRelated, $columns = ["user_tag_id", "user_tag_name"])
    {
        if (!$value) return ["value" => [], "text" => []];
        if ($way === self::wayRelated) {//方式三 已经关联出值
            //$value = ["user_tag_id" => 1, "user_tag_name" => "xxx"];
            [$userTagId, $userTagName] = $columns;
            $data = [
                "value" => $value[$userTagId] ? [$value[$userTagId]] : [],
                "text"  => $value[$userTagId] ? [["id" => $value[$userTagId], "name" => $value[$userTagName]]] : []
            ];
        } else {
            $data = static::baseWayArr("userTagQuery", $value, $way, array_combine(["id", "name"], $columns));
        }
        return $data;
    }

    //编译联系人 contact
    public static function contact($value, $way = self::wayRelated, $columns = ["user_id", "user_name", "user_avatar"])
    {
        if (!$value) return ["value" => [], "text" => []];
        if ($way === self::wayRelated) {//方式三 已经关联出值
            //$value = ["user_id" => 1, "user_name" => "xxx"];
            [$userId, $userName, $userAvatar] = $columns;
            $data = [
                "value" => $value[$userId] ? [$value[$userId]] : [],
                "text"  => $value[$userId] ? [["id" => $value[$userId], "name" => $value[$userName], "avatar" => $value[$userAvatar]]] : []
            ];
        } else {
            $data = static::baseWayArr("contactQuery", $value, $way, array_combine(["id", "name", "avatar"], $columns), "uid");
        }
        return $data;
    }

    //编译客户联系人
    public static function customerContact($value, $way = self::wayRelated, $columns = ["contact_id", "contact_name", "contact_phone"])
    {
        if (!$value) return ["value" => [], "text" => []];
        if ($way === self::wayRelated) {//方式三 已经关联出值
            [$contactId, $contactName, $contactPhone] = $columns;
            $data = [
                "value" => $value[$contactId] ? [$value[$contactId]] : [],
                "text"  => $value[$contactId] ? [["id" => $value[$contactId], "name" => $value[$contactName], "phone" => $value[$contactPhone]]] : []
            ];
        } else {
            $data = static::baseWayArr("customerContactQuery", $value, $way, array_combine(["id", "name", "phone"], $columns));
        }
        return $data;
    }

    //关联报价单
    public static function checkboxQuotation($value, $way = self::wayRelated, $columns = ["quotation_id", "quotation_num", "quoter_name", "quotation_at"])
    {
        if (!$value) return ["value" => [], "text" => []];
        if ($way === self::wayRelated) {//方式三 已经关联出值
            [$quotationId, $quotationNum, $quoterName, $quotationAt] = $columns;
            $data = [
                "value" => $value[$quotationId] ? [$value[$quotationId]] : [],
                "text"  => $value[$quotationId] ? [["id" => $value[$quotationId], "quotation_num" => $value[$quotationNum], "quoter_name" => $value[$quoterName], "quotation_at" => $value[$quotationAt]]] : []
            ];
        } else {
            $data = static::baseWayArr("projectQuotationQuery", $value, $way, array_combine(["id", "quotation_num", "quoter_name", "quotation_at"], $columns));
        }
        return $data;
    }

    //编译部门
    public static function department($value, $way = self::wayRelated, $columns = ["department_id", "department_name", "department_pid_path_name"])
    {
        //$a = [
        //    0 => [
        //        "department_id"            => 11,
        //        "department_name"          => "总经办",
        //        "department_pid_path_name" => "总经办"
        //    ]
        //];
        //["id", "department_name", "full_path"];
        //dd($value);
        if (!$value) return ["value" => [], "text" => []];
        if ($way === self::wayRelated) {//方式三 已经关联出值
            [$departmentId, $departmentName, $departmentFullPath] = $columns;
            $data = [
                "value" => $value[$departmentId] ? [$value[$departmentId]] : [],
                "text"  => $value[$departmentId] ? [["id" => $value[$departmentId], "department_name" => $value[$departmentName], "full_path" => $value[$departmentFullPath]]] : []
            ];
        } else {
            $data = static::baseWayArr("departmentQuery", $value, $way, array_combine(["id", "department_name", "full_path"], $columns));
        }
        return $data;
    }

    public static function baseWayNotArr($query, $value, $way, $columns, $keyBy = "id")
    {
        if (!$value) return ["value" => [], "text" => []];

        $data = $text = [];
        if ($way === self::wayHasOne) {//方式一详情
            //$value = 1
            $value && $text = static::$query($value, $way);
            $data = ["value" => $value, "text" => $text["name"] ?? ""];
        } elseif ($way === self::wayBelongsToMany) {//方式二 列表不带值
            //$value  =  ["10" => 123, "11" => 221];
            $queryIds = array_unique(array_values($value));
            if ($queryIds) {
                $res = static::$query($queryIds)->keyBy($keyBy)->toArray();
                foreach ($value as $id => $valQueryId) {
                    $data[$id] = ["value" => $valQueryId, "text" => $res[$valQueryId]["name"] ?? ""];
                }
            }
        } elseif ($way === self::wayRelated) {
            [$valueKey, $textKey] = $columns;
            $data = ["value" => $value[$valueKey] ?? 0, "text" => $value[$textKey] ?? ""];
        }
        return $data;

    }

    //关联客户
    public static function radioCustomer($value, $way = self::wayRelated, $columns = ["customer_id", "customer_name"])
    {
        return static::baseWayNotArr("customerQuery", $value, $way, $columns);
    }

    //关联商机
    public static function radioProject($value, $way = self::wayRelated, $columns = ["project_id", "project_name"])
    {
        return static::baseWayNotArr("projectQuery", $value, $way, $columns);
    }

    //关联订单
    public static function radioOrder($value, $way = self::wayRelated, $columns = ["order_id", "order_num"])
    {
        return static::baseWayNotArr("orderQuery", $value, $way, $columns);
    }

    //关联工单类型
    public static function radioWorkorderType($value, $way = self::wayRelated, $columns = ["workorder_type_id", "workorder_type_name"])
    {
        return static::baseWayNotArr("workorderTypeQuery", $value, $way, $columns);
    }

    public static function radioProjectStage($value, $way = self::wayRelated, $columns = ["project_stage", "project_stage_name"])
    {
        return static::baseWayNotArr("projectStageQuery", $value, $way, $columns);
    }


    //编译国家地区
    public static function address(...$data)
    {
        $value = $data[0] ?? [];
        return [
            "value"          => array_values(array_filter($value)),
            "text"           => $data[1] ?? "",//国家-地区 、 省市区
            "detail_address" => $data[2] ?? "",//详细地址
        ];
    }

    //金额类型
    public static function money(...$data)
    {
        $text = $data[1] ?? $data[0];
        return [
            "value"           => $data[0],//金额
            "text"            => number_format($text, 2),//显示金额
            "currency"        => $data[2] ?? "CNY",//币种
            "value_converted" => $data[3] ?? $data[0],//转换后金额
        ];
    }

    //数值类型
    public static function number(...$data)
    {
        return ["value" => $data[0], "text" => $data[1] ?? $data[0]];
    }

    //编译收付款账户
    public static function payingTeller(...$data)
    {
        if ($data[0] === null) return null;
        return [
            "value"        => $data[0] ?? "",//银行卡号
            "text"         => $data[0] ?? "",//银行卡号格式化
            "account_name" => $data[1] ?? "",//账户名
            "bank_name"    => $data[2] ?? "",//所属银行
            "bank_branch"  => $data[3] ?? "",//银行支行
            "account_type" => $data[4] ?? "",//账户类型
        ];
    }

    //编辑日期时间范围（上下午）
    public static function dateRangeApm($startDate, $endDate, $startTime = 0, $endTime = 0)
    {
        if ($startDate === null && $endDate === null) return null;
        $apm           = [1 => " 上午", 2 => " 下午"];
        $startTimeText = $apm[$startTime] ?? ($startTime ? " {$startTime}" : "");
        $endTimeText   = $apm[$endTime] ?? ($endTime ? " {$endTime}" : "");
        return [
            "start_date" => $startDate ?? "",//开始日期
            "end_date"   => $endDate ?? "",//结束日期
            "start_time" => $startTime ?? 0,//开始时间
            "end_time"   => $endTime ?? 0,//结束时间
            "text"       => "{$startDate}{$startTimeText} ~ {$endDate}{$endTimeText}",//2024-05-11 上午 ~ 2024-05-11 下午
        ];
    }

    //编译电话号码
    public static function telephone(...$data)
    {
        $prefix = $data[1] ?? "+86";
        return [
            "value"      => $data[0],//电话号码
            "prefix"     => $prefix,
            "phone_type" => in_array($prefix, ["+86", "+86 "]) ? "1" : "2",
            //"direct_dial" => $data[3] ?? false,
        ];
    }

    //编译单选
    public static function radio(...$data)
    {
        return [
            "value" => $data[0],//单选值
            "text"  => $data[1]//单选名称
        ];
    }

    //编译单选
    public static function checkbox(...$data)
    {
        return [
            "value" => $data[0],//复选选值
            "text"  => $data[1]//复选名称
        ];
    }

    public static function default($data)
    {
        return json_validate($data) ? json_decode($data, true) : $data;
    }

    public static function __callStatic($method, $args)
    {
        $method = toCamelCase($method);
        if (method_exists(static::class, $method)) {
            // 调用转换后的方法
            return call_user_func_array([static::class, $method], $args);
        } else {
            // 如果方法不存在，则调用默认的处理方法
            return static::default(...$args);
        }

        //throw new \BadMethodCallException("Method {$method} does not exist.");
    }

}
