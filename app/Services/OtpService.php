<?php
namespace App\Services;

use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function issue(string $phone,string $purpose): OtpCode
    {
        $latest=OtpCode::where('phone',$phone)->where('purpose',$purpose)->latest()->first();
        if($latest?->resend_available_at?->isFuture()){
            throw ValidationException::withMessages(['phone'=>[__('api.otp_resend_not_available')]]);
        }
        $length=config('elder.otp.length',6);
        $code=str_pad((string)random_int(0,(10**$length)-1),$length,'0',STR_PAD_LEFT);
        $otp=OtpCode::create([
            'phone'=>$phone,'purpose'=>$purpose,'code_hash'=>Hash::make($code),
            'expires_at'=>now()->addMinutes(config('elder.otp.ttl_minutes',5)),
            'resend_available_at'=>now()->addSeconds(config('elder.otp.resend_seconds',60)),
            'attempts'=>0,
        ]);
        Log::info('Development OTP',['phone'=>$phone,'purpose'=>$purpose,'code'=>$code]);
        return $otp;
    }

    public function verify(string $phone,string $purpose,string $code): OtpCode
    {
        $otp=OtpCode::where('phone',$phone)->where('purpose',$purpose)->whereNull('verified_at')->latest()->first();
        if(!$otp) throw ValidationException::withMessages(['otp'=>[__('api.otp_invalid')]]);
        if($otp->expires_at->isPast()) throw ValidationException::withMessages(['otp'=>[__('api.otp_expired')]]);
        if($otp->attempts>=config('elder.otp.max_attempts',5)) throw ValidationException::withMessages(['otp'=>[__('api.otp_attempts_exceeded')]]);
        if(!Hash::check($code,$otp->code_hash)){
            $otp->increment('attempts');
            throw ValidationException::withMessages(['otp'=>[__('api.otp_invalid')]]);
        }
        $otp->update(['verified_at'=>now()]);
        return $otp;
    }
}
