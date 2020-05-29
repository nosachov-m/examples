<?php

namespace App\Http\Controllers\Chat;

use App\ChatGroup;
use App\ChatGroupHasUsers;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\User;
use Spatie\Permission\Traits\HasRoles;

class ChatGroupController extends Controller
{

    use HasRoles;

    /**
     * Get all Chat Groups list to admin index view.
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\Response
     */
    public function getAdminChatGroupsListAjax(Request $request)
    {
        try{
            $chat_groups = ChatGroup::all();
        }
        catch(\Exception $e){
            return response(json_encode(
                [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ]),
                200);
        }

        return response(json_encode(
            [
                'status' => 'success',
                'chat_groups' => $chat_groups
            ]),
            200);
    }

    /**
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\ChatGroup  $chatGroup
     * @return \Illuminate\Http\Response
     */
    public function createChatGroupAjax(Request $request, ChatGroup $chatGroup)
    {
        //TODO проверка на длину полей
        try{
            $params = $request->all();
            $user_id = Auth::id();
            $params['created_by'] = $user_id;
            $chatGroup->fill($params);
            $chatGroup->save();
        }
        catch(\Exception $e){
            return response(json_encode(
                [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ]),
                200);
        }

        return response(json_encode(
            [
                'group_id' => $chatGroup->id,
                'status' => 'success',
                'params' => $params
            ]),
            200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getChatGroupInfoAjax(Request $request)
    {
        try{
            $chat_group_id = $request->id;
            $chat_group = ChatGroup::find($chat_group_id);

            $managers = User::manager()->get();

            $chat_members = [];
            $chat_admins = [];
            foreach($managers as $manager){
                if($manager->hasRole('admin')){
                    $chat_admins[] = $manager;
                } else {
                    $chat_members[] = $manager;
                }
            }

            $chat_group_has_users = ChatGroupHasUsers::where(['chat_group_id' => $chat_group_id, 'is_ban' => 0])->get()->keyBy('user_id');
        }
        catch(\Exception $e){
            return response(json_encode(
                [
                    'message' => $e->getMessage(),
                    'status' => 'error',
                ]),
                200);
        }

        return response(json_encode(
            [
                'chat_group' => $chat_group,
                'admins' => $chat_admins,
                'managers' => $chat_members,
                'chat_group_has_users' => $chat_group_has_users,
                'status' => 'success',
            ]),
            200);

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\ChatGroup $chatGroup
     * @return \Illuminate\Http\Response
     */
    public function editChatGroupInfoAjax(Request $request)
    {
        $id = $request->id;
        if(!$id){
            return response(json_encode(
                [
                    'message' => 'Must pass id argument',
                    'status' => 'error',
                ]),
                200);
        }

        try{
            $params = $request->all();
            $chat_group = ChatGroup::find($id);
            $chat_group->fill($params);
            $chat_group->save();
        }
        catch (\Exception $e){
            return response(json_encode(
                [
                    'message' => $e->getMessage(),
                    'status' => 'error',
                ]),
                200);
        }

        return response(json_encode(
            [
                'status' => 'success',
                'message' => 'Сохранено',
                'chat_group' => $chat_group
            ]),
            200);

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\ChatGroup $chatGroup
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ChatGroup $chatGroup)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\ChatGroup  $chatGroup
     * @return \Illuminate\Http\Response
     */
    public function destroy(ChatGroup $chatGroup)
    {
        //
    }

    /**
     * Get shortened information about chat group.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getChatGroupSimpleInfoAjax(Request $request)
    {
        try{
            $chat_group_id = $request->id;
            $chat_group = ChatGroup::find($chat_group_id);
        }
        catch(\Exception $e){
            return response(json_encode(
                [
                    'message' => $e->getMessage(),
                    'status' => 'error',
                ]),
                200);
        }

        return response(json_encode(
            [
                'chat_group' => $chat_group,
                'status' => 'success',
            ]),
            200);

    }
}
