<div class="back-row"><a class="back-link" href="{{ route('admin.plans.index') }}">← กลับไปแพ็กเกจทั้งหมด</a></div>
@include('admin.pages.partials.plan-form', ['plan' => $plan, 'formTitle' => 'ข้อมูลแพ็กเกจ '.$plan->name, 'formAction' => route('admin.plans.update', $plan), 'submitLabel' => 'บันทึกแพ็กเกจ'])
