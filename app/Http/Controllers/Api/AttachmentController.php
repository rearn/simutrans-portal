<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Attachments as AttachmentsResource;
use App\Models\Attachment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class AttachmentController extends Controller
{
    /**
     * アップロード一覧
     */
    public function my()
    {
        $user = Auth::user()->load('myAttachments', 'myAttachments.attachmentable');
        return new AttachmentsResource($user->myAttachments);
    }
    /**
     * アップロード一覧
     */
    public function myimage()
    {
        $user = Auth::user()->load('myAttachments', 'myAttachments.attachmentable');
        return new AttachmentsResource($user->myAttachments->filter(function($attachment) {
            return $attachment->is_image;
        }));
    }

    public function upload(Request $request)
    {
        abort_unless($request->hasFile('file'), 400);

        $user = Auth::user();
        Attachment::createFromFile($request->file('file'), $user->id);

        $user->load('myAttachments');
        return new AttachmentsResource($user->myAttachments);
    }
    public function delete($id)
    {
        Attachment::where('user_id', Auth::id())->delete($id);
    }
}