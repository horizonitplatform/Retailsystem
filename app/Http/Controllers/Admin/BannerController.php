<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Banner;
use Illuminate\Http\Request;
use Image;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::where('type', 'banner')->get();
        $event = Banner::where('type', 'event')->first();

        return view('admin.banner', [
            'banners' => $banners,
            'event' => $event,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->image;
        if ($request->hasFile('image')) {
            for ($i = 0; $i < sizeof($data); ++$i) {
                $randomstr = substr(md5(microtime()), rand(0, 26), 7);
                $banner = new Banner();
                $image = $request->file('image')[$i];
                $filename = $image->getClientOriginalName();
                $image_resize = Image::make($image->getRealPath());
                $image_resize->resize(741, 321);
                $image_resize->save(public_path('banners/'.$randomstr.$filename));
                $banner->src = 'banners/'.$randomstr.$filename;
                $banner->type = 'banner';
                $banner->save();
            }
        }

        if ($request->hasFile('event')) {
            $event = Banner::where('type', 'event')->first();
            if ($event == null) {
                $newEvent = new Banner();
                $randomstr = substr(md5(microtime()), rand(0, 26), 7);
                $image = $request->file('event');
                $filename = $image->getClientOriginalName();
                $image_resize = Image::make($image->getRealPath());
                $image_resize->resize(488.462, 431.425);
                $image_resize->save(public_path('banners/'.$randomstr.$filename));
                $newEvent->src = 'banners/'.$randomstr.$filename;
                $newEvent->type = 'event';
                $newEvent->save();
            } else {
                $image = $request->file('event');
                $randomstr = substr(md5(microtime()), rand(0, 26), 7);
                $filename = $image->getClientOriginalName();
                $image_resize = Image::make($image->getRealPath());
                $image_resize->resize(488.462, 431.425);
                $image_resize->save(public_path('banners/'.$randomstr.$filename));
                $event->src = 'banners/'.$randomstr.$filename;
                $event->save();
            }
        }

        return back();
    }

    public function destroy(Request $request)
    {
        $src = public_path($request->src);
        $id = ($request->id);
        if (!unlink($src)) {
            dd('error');
        } else {
            $deletedRows = Banner::find($id);
            $deletedRows->delete();
        }

        return back()->with('message', 'Delete successful');
    }
}
