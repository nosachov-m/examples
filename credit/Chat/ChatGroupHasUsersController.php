<?php

namespace App\Http\Controllers\Chat;

use App\ChatGroup;
use App\ChatGroupHasUsers;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\User;
use App\Events\Chat\ChatHasUserGranted;
use App\Events\Chat\ChatHasUserRevoked;

class ChatGroupHasUsersController extends Controller
{
    /**
     * Get a listing of the users of all Chat Groups.
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

    }

    /**
     * Creating or updating record for user added to Chat Group
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\Response
     */
    public function addUserToChatGroupAjax(Request $request)
    {
        if(!$request->has('chat_group_id') OR !$request->has('user_id')){
            return response(json_encode(
                [
                    'message' => 'Not enough data',
                    'status' => 'error',
                ]),
                200);
        }

        try{
            $chat_group_id = $request->chat_group_id;
            $user_id = $request->user_id;
            $chat_group_user = ChatGroupHasUsers::firstOrNew(['chat_group_id' => $chat_group_id, 'user_id' => $user_id]);
            $chat_group_user->fill($request->all());
            $chat_group_user->save();
            //Broadcast to socket server..
            if($chat_group_user->is_ban){
                event(
                    new ChatHasUserRevoked($chat_group_user)
                );
            } else {
                event(
                    new ChatHasUserGranted($chat_group_user)
                );
            }

            return response(json_encode(
                [
                    'message' => 'Пользователь изменён!',
                    'status' => 'success',
                    'chat_group_user' => $chat_group_user
                ]),
                200);
        }
        catch(\Exception $e){
            return response(json_encode(
                [
                    'message' => $e->getMessage(),
                    'status' => 'error',
                ]),
                200);
        }

    }


    /**
     * Get a listing of the users of all Chat Groups.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\ChatGroupHasUsers  $chatGroupHasUsers
     * @return \Illuminate\Http\Response
     */
    public function getChatGroupUsersAjax(Request $request, ChatGroupHasUsers $chatGroupHasUsers)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\ChatGroupHasUsers  $chatGroupHasUsers
     * @return \Illuminate\Http\Response
     */
    public function edit(ChatGroupHasUsers $chatGroupHasUsers)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\ChatGroupHasUsers  $chatGroupHasUsers
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ChatGroupHasUsers $chatGroupHasUsers)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\ChatGroupHasUsers  $chatGroupHasUsers
     * @return \Illuminate\Http\Response
     */
    public function destroy(ChatGroupHasUsers $chatGroupHasUsers)
    {
        //
    }
}
