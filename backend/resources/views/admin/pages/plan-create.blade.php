<div class="back-row"><a class="back-link" href="{{ route('admin.plans.index') }}">← กลับไปแพ็กเกจทั้งหมด</a></div>
@include('admin.pages.partials.plan-form', ['plan' => null, 'formTitle' => 'ข้อมูลแพ็กเกจใหม่', 'formAction' => route('admin.plans.store'), 'submitLabel' => 'สร้างแพ็กเกจ'])
