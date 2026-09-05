<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.frontend_url') ?: config('app.url')">
            {{ config('app.name') }}
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            GamoryID — ระบบจัดการสต๊อกและการขายไอดีเกมสำหรับร้านค้า

            ผู้ให้บริการ: {{ config('legal.controller_name') }} · {{ config('legal.controller_address') }}
            ติดต่อสอบถาม: {{ config('legal.controller_email') }}

            อีเมลฉบับนี้ส่งจากระบบอัตโนมัติ กรุณาอย่าตอบกลับ

            © {{ date('Y') }} GamoryID · สงวนลิขสิทธิ์
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
