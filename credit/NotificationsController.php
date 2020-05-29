<?php

namespace App\Http\Controllers;

use App\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Notification;
use App\Task;
use App\CreditRequest;
use App\Chat;
use App\Action;

class NotificationsController extends Controller
{

    public function getUnseenNotifications(Request $request)
    {
        try{
            $user = Auth::user();
            $notifications = [];
            $tasks = Notification::where(['user_id' => $user->id, 'model' => Task::class, 'seen' => 0])->count();
            $requests = Notification::where(['user_id' => $user->id, 'model' => CreditRequest::class, 'seen' => 0])->count();
            $chats = Notification::where(['user_id' => $user->id, 'model' => ChatMessage::class, 'seen' => 0])->count();
            $notifications['tasks'] = $tasks;
            $notifications['requests'] = $requests;
            $notifications['chats'] = $chats;
            return response(json_encode(
                [
                    'status' => 'success',
                    'notifications' => $notifications,
                ]),
                200);
        } catch (\Exception $e){
            return response(json_encode(
                [
                    'status' => 'error',
                    'error_message' => $e->getMessage()
                ]),
                200);
        }


    }

    public function markAsSeenChatMessage(Request $request)
    {
        $model_id = $request->id;
        $user = Auth::user();
        Notification::where(['model' => ChatMessage::class, 'model_id' => $model_id, 'user_id' => $user->id])->update(['seen' => 1]);
        return response(json_encode(
            [
                'status' => 'success',
                'message' => 'seen'
            ]),
            200);

    }

    public function getNotificationsAjax(Request $request)
    {
        try{
            $params = $request->all();

            $columns = $params['columns'];
            $order_column_number = $params['order'][0]['column'];
            $order_column = $columns[$order_column_number]['data'];
            $order_by = $params['order'][0]['dir'];

            $user = Auth::user();

            $cnt = Action::whereIn('action_type', Action::$notifiable)->count();
            $recordsTotal = $cnt;

            $start = $request->start ? $request->start : 0;
            $length = $request->length ? $request->length : 50;

            $actions = Action::whereIn('action_type', Action::$notifiable)
                ->orderBy($order_column, $order_by)
                ->offset($start)->limit($length)
                ->get();

            $users = [];

            $data = [];
            foreach($actions as $action){
                $route = array_key_exists($action->model, \App\Action::$_routes) ?  route(\App\Action::$_routes[$action->model], [$action->model_id]) : '#';

                $data[] = [
                    'id' => '<a href="'.$route.'" target="_blank">'.$action->id.'</a>',
                    'date' => \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $action->created_at)->format('d-m-Y H:i:s'),
                    'text' => '<a href="'.$route.'" target="_blank">'.$action->text.'</a>'

                ];
            }

            return json_encode(['data' => $data, 'recordsTotal' => $recordsTotal, 'recordsFiltered' => $cnt, 'params' => $params]);
        } catch(\Exception $e){
            return response(json_encode(
                [
                    'status' => 'error',
                    'error_message' => $e->getMessage()
                ]),
                200);
        }


    }



}