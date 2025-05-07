<?php

namespace lanerp\dong\CustomField;


use App\Models\Approval;
use App\Models\ApprovalSet;
use App\Models\AttendanceHolidayBalance;
use App\Models\AttendanceUserHolidayRecords;
use App\Models\AttendanceUserOvertimeRecords;
use App\Models\AttendanceUserTravelRecords;
use App\Models\CustomForm;
use App\Models\FinancePayable;
use App\Services\Api\Finance\PayableService;

class ControlEvent
{
    protected Form $form;

    public function __construct(Form $form)
    {
        $this->form = $form;
    }

//-----------------------审批通过控件事件------------------------

    /**
     * Notes:控件组事件触发
     * Date: 2024/11/27
     * @param $pkId
     */
    public function controlEventTrigger($pkId): void
    {
        $eventControl = CustomForm::getRequestInfo($this->form->getTable(), $pkId, "event_control");
        //$eventControl = $this->form->eventControl;
        if ($eventControl) {
            foreach ($eventControl as $v) {
                /**
                 * event_control_payable
                 */
                if (method_exists($this, "event_" . $v["event_control"])) {
                    $this->{"event_" . $v["event_control"]}($pkId, $v);
                }
            }
        }
        return;

    }

    // 加班控件审批通过
    public function event_control_overtime($pkId, $v): void
    {
        AttendanceUserOvertimeRecords::controlEvent(
            $this->form->getUid(),
            $this->form->getTable(),
            $pkId,
            $this->form->getApprovalSetId()
        );
        return;
    }

    // 请假控件组审批通过
    public function event_control_rest($pkId, $v): void
    {
        AttendanceUserHolidayRecords::controlEvent(
            $this->form->getUid(),
            $this->form->getTable(),
            $pkId,
            $this->form->getApprovalSetId()
        );
        return;
    }

    // 出差控件组审批通过
    public function event_control_travel($pkId, $v): void
    {
        AttendanceUserTravelRecords::controlEvent(
            $this->form->getUid(),
            $this->form->getTable(),
            $pkId,
            $this->form->getApprovalSetId()
        );
        return;
    }

    // 采购控件审批通过
    public function event_control_payable($pkId, $v): void
    {
        FinancePayable::controlEvent(
            $this->form->getUid(),
            $this->form->getTable(),
            $pkId,
            $this->form->getApprovalSetId()
        );
        return;
    }

//-----------------------审批取消控件事件------------------------

    /**
     * Notes:取消控件组事件触发
     * Date: 2024/11/27
     * @param $pkId
     */
    public function cancelControlEventTrigger($pkId): void
    {
        $eventControl = CustomForm::getRequestInfo($this->form->getTable(), $pkId, "event_control");
        if ($eventControl) {
            foreach ($eventControl as $v) {
                /**
                 * cancel_event_control_stock_movement
                 */
                if (method_exists($this, "cancel_event_" . $v["event_control"])) {
                    $this->{"cancel_event_" . $v["event_control"]}($pkId, $v);
                }
            }
        }
        return;
    }

    // 请假的 用户取消和驳回
    public function cancel_event_control_rest($pkId)
    {

        AttendanceHolidayBalance::cancelControlEvent(
            $this->form->getUid(),
            $this->form->getTable(),
            $pkId,
            $this->form->getApprovalSetId()
        );
        return;

    }


//-----------------------审批创建控件事件------------------------

    /**
     * Notes:控件组审批创建事件触发
     * Date: 2024/11/27
     * @param $pkId
     */
    public function frontControlEventTrigger($pkId): void
    {
        $frontEventControl = $this->form->eventControl;
        if (!empty($frontEventControl)) {
            foreach ($frontEventControl as $v) {
                /**
                 * front_event_control_stock_movement
                 */
                if (method_exists($this, "front_event_" . $v["event_control"])) {
                    $this->{"front_event_" . $v["event_control"]}($pkId, $v);
                }
            }
        }
        return;
    }

    public function front_event_control_overtime($pkId)
    {
        //加班审批创建逻辑
        /*
        Overtime::frontControlEvent(
            $this->form->getUid(),//申请人uid
            $this->form->getTable(),
            $pkId,
            CustomForm::getRequestInfo($this->form->getTable(), $pkId, "created_at")//申请时间
        );
        return;
        */
    }

    // 请假审批发起的事件，需要把余额暂时占用。
    public function front_event_control_rest($pkId)
    {
        AttendanceHolidayBalance::frontControlEvent(
            $this->form->getUid(),
            $this->form->getTable(),
            $pkId,
            $this->form->getApprovalSetId()
        );
        return;
    }

}
