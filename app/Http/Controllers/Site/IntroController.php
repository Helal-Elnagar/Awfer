<?php
namespace App\Http\Controllers\Site;

use App\Models\Fqs;
use App\Models\City;
use App\Models\Plan;
use App\Models\Social;
use App\Models\IntroFqs;
use App\Models\ContactUs;
use App\Models\SiteSetting;
use App\Models\ClientReview;
use App\Models\IntroService;
use App\Models\IntroMessages;
use App\Services\SettingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\sendMessageRequest;

class IntroController extends Controller
{
    public function index()
    {
        $locale        = app()->getLocale();
        $about         = SiteSetting::where(['key' => 'about_' . $locale])->first()->value;
        $fqs           = Fqs::all();
        $features      = IntroService::take(6)->get();
        $clientReviews = ClientReview::all();
        $contacts      = SiteSetting::whereIn('key', ['email', 'phone', 'whatsapp'])->get(['id', 'key', 'value']);
        $socials       = Social::latest()->get();
        $socialLinks   = $socials->mapWithKeys(function($s){
            return [strtolower($s->name) => $s->link];
        })->toArray();
        $cities        = City::all();
        $appStoreLink  = SiteSetting::where('key', 'app_store_link')->first()->value ?? '#';
        $googlePlayLink = SiteSetting::where('key', 'google_play_link')->first()->value ?? '#';

        return view('site.index', compact('about', 'fqs', 'features', 'clientReviews', 'contacts', 'socials', 'socialLinks', 'cities', 'appStoreLink', 'googlePlayLink'));
    }

    public function about()
    {
        $locale = app()->getLocale();

        $about    = SiteSetting::where(['key' => 'about_' . $locale])->first()->value;
        $contacts = SiteSetting::whereIn('key', ['email', 'phone', 'whatsapp'])->get(['id', 'key', 'value']);
        $socials  = Social::latest()->get();
        $socialLinks   = $socials->mapWithKeys(function($s){
            return [strtolower($s->name) => $s->link];
        })->toArray();
        $cities   = City::all();
        return view('site.pages.about', compact('about', 'contacts', 'socials', 'socialLinks', 'cities'));

    }

    public function fqs()
    {
        $locale = app()->getLocale();

        $fqs           = Fqs::all();
        $contacts = SiteSetting::whereIn('key', ['email', 'phone', 'whatsapp'])->get(['id', 'key', 'value']);
        $socials  = Social::latest()->get();
        $socialLinks   = $socials->mapWithKeys(function($s){
            return [strtolower($s->name) => $s->link];
        })->toArray();
        $cities   = City::all();
        return view('site.pages.fqs', compact('fqs', 'contacts', 'socials', 'socialLinks', 'cities'));

    }

     public function terms()
    {
        $locale = app()->getLocale();

        $terms    = SiteSetting::where(['key' => 'terms_' . $locale])->first()->value;
        $contacts = SiteSetting::whereIn('key', ['email', 'phone', 'whatsapp'])->get(['id', 'key', 'value']);
        $socials  = Social::latest()->get();
        $socialLinks   = $socials->mapWithKeys(function($s){
            return [strtolower($s->name) => $s->link];
        })->toArray();
        $cities   = City::all();
        return view('site.pages.terms', compact('terms', 'contacts', 'socials', 'socialLinks', 'cities'));

    }

     public function privacy()
    {
        $locale = app()->getLocale();

        $privacy    = SiteSetting::where(['key' => 'privacy_' . $locale])->first()->value;
        $contacts = SiteSetting::whereIn('key', ['email', 'phone', 'whatsapp'])->get(['id', 'key', 'value']);
        $socials  = Social::latest()->get();
        $socialLinks   = $socials->mapWithKeys(function($s){
            return [strtolower($s->name) => $s->link];
        })->toArray();
        $cities   = City::all();
        return view('site.pages.privacy', compact('privacy', 'contacts', 'socials', 'socialLinks', 'cities'));

    }

    public function deletePolicy()
    {
        $locale = app()->getLocale();

        $deletePolicy = SiteSetting::where(['key' => 'delete_policy_' . $locale])->first()->value;
        $contacts = SiteSetting::whereIn('key', ['email', 'phone', 'whatsapp'])->get(['id', 'key', 'value']);
        $socials  = Social::latest()->get();
        $socialLinks   = $socials->mapWithKeys(function($s){
            return [strtolower($s->name) => $s->link];
        })->toArray();
        $cities   = City::all();
        return view('site.pages.delete-policy', compact('deletePolicy', 'contacts', 'socials', 'socialLinks', 'cities'));
    }

    public function sendMessage(sendMessageRequest $request)
    {
        ContactUs::create($request->validated());
        return response()->json(['status' => 'done', 'message' => __('intro_site.message_sent')]);
    }

    /***************** change lang  *****************/
    public function SetLanguage($lang)
    {
        if (in_array($lang, ['ar', 'en'])) {

            if (session()->has('lang')) {
                session()->forget('lang');
            }

            session()->put('lang', $lang);

        } else {
            if (session()->has('lang')) {
                session()->forget('lang');
            }

            session()->put('lang', 'ar');
        }

//        dd(session( 'lang' ));
        return back();
    }
}
