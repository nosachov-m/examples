<?php

namespace App\Http\Controllers;

use App\ChatGroup;
use Illuminate\Http\Request;
use Auth;
use App\Chat;
use App\User;
use App\ChatGroupHasUsers;
use App\ChatMessage;
use App\Events\Chat\NewChatMessage;
use App\Notification;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        //все открытые группы, мои группы, контакты-менеджеры

        $open_groups = ChatGroup::where(['is_private' => 0, 'is_active' => 0])->get();
        $my_groups_id = ChatGroupHasUsers::where(['user_id' => $user->id, 'is_ban' => 0])->pluck('chat_group_id')->toArray();
        $my_groups = ChatGroup::wherein('id', $my_groups_id)->get();
        if($user->hasRole('admin')){
            $open_groups = [];
            $my_groups = ChatGroup::all();
        }

        $admin_groups = ChatGroup::all();

        $direct_contacts = User::manager()->where('id', '!=', $user->id)->orderBy('lastname')->get();
        $history = [];

        foreach($open_groups as $open_group){
            $message = ChatMessage::with('user', 'chatgroup', 'recipient')->where('chat_group_id', '=', $open_group->id)->get()->last();
            if($message){
                $history[$message->id] = $message;
            }
        }
        foreach($my_groups as $my_group){
            $message = ChatMessage::with('user', 'chatgroup', 'recipient')->where('chat_group_id', '=', $my_group->id)->get()->last();
            if($message){
                $history[$message->id] = $message;
            }
        }

        foreach($direct_contacts as $direct_contact){
            $message = ChatMessage::with('user')->where('is_direct', '=', 1)
                ->whereIn('user_from_id', [$user->id, $direct_contact->id])
                ->whereIn('user_to_id', [$user->id, $direct_contact->id])
                ->get()->last();
            if($message){
                $history[$message->id] = $message;
            }
        }

        $unseens = [];
        $unseens['groups'] = [];
        $unseens['directs'] = [];
        foreach($direct_contacts as $direct_contact){
            $directs = ChatMessage::with('user')->where('is_direct', '=', 1)
                ->whereIn('user_from_id', [$user->id, $direct_contact->id])
                ->whereIn('user_to_id', [$user->id, $direct_contact->id])
                ->pluck('id')->toArray();

            $unseen = Notification::where('seen', '=', 0)
                ->where('model', '=', ChatMessage::class)
                ->whereIn('model_id', $directs)->count();
            $unseens['directs'][$direct_contact->id] = $unseen;
        }

        foreach($open_groups as $open_group){
            $messages = ChatMessage::where('chat_group_id', '=', $open_group->id)->pluck('id')->toArray();
            $unseen = Notification::where('seen', '=', 0)
                ->where('model', '=', ChatMessage::class)
                ->where('user_id', '=', $user->id)
                ->whereIn('model_id', $messages)->count();
            $unseens['groups'][$open_group->id] = $unseen;
        }
        foreach($my_groups as $my_group){
            $messages = ChatMessage::where('chat_group_id', '=', $my_group->id)->pluck('id')->toArray();
            $unseen = Notification::where('seen', '=', 0)
                ->where('model', '=', ChatMessage::class)
                ->where('user_id', '=', $user->id)
                ->whereIn('model_id', $messages)->count();
            $unseens['groups'][$my_group->id] = $unseen;
        }

        krsort($history);


        return view('chat.index', [
            'user' => $user,
            'messages' => [],
            'history_chats' => $history,
            'open_groups' => $open_groups,
            'my_groups' => $my_groups,
            'direct_contacts' => $direct_contacts,
            'admin_groups' => $admin_groups,
            'unseens' => $unseens

        ]);
    }

    public function getRoomMessagesAjax(Request $request)
    {
        $user = Auth::user();
        $user_id = $user->id;
        $room_id = $request->room_id;
        $recipient = User::find($room_id);
        $recipient_avatar = $recipient->avatar;
        $recipient_avatar = $recipient->avatar ? Storage::url($recipient->avatar) : asset('/static/images/avatars/no-avatar.png');
        $recipient_author = $recipient->lastname.' '.$recipient->firstname;
        $user_author = $user->lastname.' '.$user->firstname;
        if(!$room_id){
            $messages = Chat::where('room_id', '=', $user_id.'-0')->orWhere('room_id', '=', '0-'.$user_id)->orWhere('room_id', '=', '')->get();
        } else {
            $messages = Chat::where('room_id', '=', $user_id.'-'.$room_id)->orWhere('room_id', '=', $room_id.'-'.$user_id)->get();
        }
        $result = [];
        foreach($messages as $message){
            $row = [];
            $row['user_id'] = $message->user_id;
            $row['avatar'] = $message->user_id == $user_id ? Storage::url($user->avatar) : Storage::url($recipient_avatar);
            $row['author'] = $message->user_id == $user_id ? $user_author : $recipient_author;
            $row['message'] = $message->message;
            $row['time'] = $message->created_at;
            $result[] = $row;
        }
        return json_encode(
            [
                'messages' => $result,
                'recipient_avatar' => $recipient_avatar ? $recipient_avatar : '',
                'recipient_author' => $room_id == 0 ? 'Общий чат' : $recipient_author,
            ]);
    }

    public function newChatMessageAjax(Request $request, ChatMessage $chatMessage, Notification $notification)
    {
        try{
            $chatMessage->fill($request->all());
            $user = Auth::user();
            $chatMessage->user_from_id = $user->id;
            $chatMessage->save();

            $chat_group_users = [];
            $chat_group = [];

            if($request->has('user_to_id') AND !empty($request->user_to_id)){
                $notification->user_id = $request->user_to_id;
                $notification->model = ChatMessage::class;
                $notification->model_id = $chatMessage->id;
                $notification->save();
            } else {
                $chat_group = ChatGroup::find($request->chat_group_id);
                if($chat_group->is_private){
                    $chat_group_users = ChatGroupHasUsers::where('chat_group_id', '=', $request->chat_group_id)->where('user_id', '!=', $user->id)->where('is_ban', '=', 0)->get();
                } else {
                    //отправляем всем
                    $chat_group_users = User::manager()->where('id', '!=', $user->id)->get();
                }

                foreach($chat_group_users as $chat_group_user){
                    Notification::create(['user_id' => $chat_group_user->id, 'model' => ChatMessage::class, 'model_id' => $chatMessage->id]);
                }
            }

            event(
                new NewChatMessage($chatMessage)
            );
        }catch(\Exception $e){
            return response(json_encode(
                [
                    'status' => 'error',
                    'error_message' => $e->getMessage()
                ]),
                200);
        }

        return response(json_encode(
            [
                'status' => 'success',
                'chatMessage' => $chatMessage,
                'chat_group_users' => $chat_group_users,
                'chat_id' => $request->chat_group_id,
                'chat_group' => $chat_group
            ]),
            200);
    }

    public function getDirectMessagesAjax(Request $request)
    {
        try{
            $user = Auth::user();
            $contact = User::find($request->contact);
            $avatar = $contact->avatar ? Storage::url($contact->avatar) : asset('/static/images/avatars/no-avatar.png');

            $messages = ChatMessage::where('is_direct', '=', 1)
                ->whereIn('user_from_id', [$user->id, $contact->id])
                ->whereIn('user_to_id', [$user->id, $contact->id])->get();

            $seen = ChatMessage::where('is_direct', '=', 1)
                ->whereIn('user_from_id', [$user->id, $contact->id])
                ->whereIn('user_to_id', [$user->id, $contact->id])->pluck('id')->toArray();

            Notification::where('model', '=', ChatMessage::class)->whereIn('model_id', $seen)->update(['seen' => 1]);
            $unseen = Notification::where(['user_id' => $user->id, 'model' => ChatMessage::class, 'seen' => 0])->count();


            return response(json_encode(
                [
                    'status' => 'success',
                    'messages' => $messages,
                    'contact' => $contact,
                    'myself' => $user,
                    'unseen_chat' => $unseen,
                    'avatar' => $avatar
                ]),
                200);
        } catch(\Exception $e){
            return response(json_encode(
                [
                    'status' => 'error',
                    'message' => $e->getMessage()
                ]),
                200);
        }
    }

    public function getGroupMessagesAjax(Request $request)
    {
        try{
            $user = Auth::user();
            $messages = ChatMessage::with('user')
                ->where('chat_group_id', '=', $request->chat_group_id)->get();

            $seen = ChatMessage::where('chat_group_id', '=', $request->chat_group_id)->pluck('id')->toArray();

            Notification::where('model', '=', ChatMessage::class)->whereIn('model_id', $seen)->update(['seen' => 1]);
            $unseen = Notification::where(['user_id' => $user->id, 'model' => ChatMessage::class, 'seen' => 0])->count();
            if(!$unseen){
                $unseen = 0;
            }


            return response(json_encode(
                [
                    'status' => 'success',
                    'messages' => $messages,
                    'unseen_chat' => $unseen
                ]),
                200);
        } catch(\Exception $e){

        }
    }

    public function gethistory(Request $request)
    {
		//test user
        $id = 73;

        $open_groups = ChatGroup::where(['is_private' => 0, 'is_active' => 0])->get();
        $my_groups_id = ChatGroupHasUsers::where(['user_id' => $id, 'is_ban' => 0])->pluck('chat_group_id')->toArray();
        $my_groups = ChatGroup::wherein('id', $my_groups_id)->get();
        $direct_contacts = User::manager()->where('id', '!=', $id)->orderBy('lastname')->get();

        $history = [];
        foreach($open_groups as $open_group){
            $message = ChatMessage::with('user', 'chatgroup')->where('chat_group_id', '=', $open_group->id)->get()->last();
            if($message){
                $history[$message->id] = $message;
            }
        }
        foreach($my_groups as $my_group){
            $message = ChatMessage::with('user')->where('chat_group_id', '=', $my_group->id)->get()->last();
            if($message){
                $history[$message->id] = $message;
            }
        }

        foreach($direct_contacts as $direct_contact){
            $message = ChatMessage::with('user')->where('is_direct', '=', 1)
                ->whereIn('user_from_id', [$id, $direct_contact->id])
                ->whereIn('user_to_id', [$id, $direct_contact->id])
                ->get()->last();
            if($message){
                $history[$message->id] = $message;
            }
        }
        krsort($history);


        return view('chat.index', [
            'user' => $user,
            'messages' => [],
            'history_chats' => [],
            'open_groups' => $open_groups,
            'my_groups' => $my_groups,
            'direct_contacts' => $direct_contacts,
            'admin_groups' => $admin_groups,

        ]);

    }

}
