<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Response;
use App\Models\Favorite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Pusher\Pusher;
use Exception;

class MessagesController extends Controller
{

    /**
     * Allowed extensions to upload attachment
     * [Images / Files]
     *
     * @var
     */
    public static $allowed_images = array('png','jpg','jpeg','gif');
    public static $allowed_files  = array('zip','rar','txt');

    /**
     * This method returns the allowed image extensions
     * to attach with the message.
     *
     * @return array
     */
    public function getAllowedImages(){
        return self::$allowed_images;
    }

    /**
     * This method returns the allowed file extensions
     * to attach with the message.
     *
     * @return array
     */
    public function getAllowedFiles(){
        return self::$allowed_files;
    }

    /**
     * Returns an array contains messenger's colors
     *
     * @return array
     */
    public function getMessengerColors(){
        return [
            '1' => '#2180f3',
            '2' => '#2196F3',
            '3' => '#00BCD4',
            '4' => '#3F51B5',
            '5' => '#673AB7',
            '6' => '#4CAF50',
            '7' => '#FFC107',
            '8' => '#FF9800',
            '9' => '#ff2522',
            '10' => '#9C27B0',
        ];
    }

    /**
     * Pusher connection
     */
    public function pusher()
    {
        return new Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            config('broadcasting.connections.pusher.options')
        );
    }

    /**
     * Trigger an event using Pusher
     *
     * @param string $channel
     * @param string $event
     * @param array $data
     * @return void
     */
    public function push($channel, $event, $data)
    {
        return $this->pusher()->trigger($channel, $event, $data);
    }

    /**
     * Authinticate the connection for pusher
     *
     * @param Request $request
     * @return void
     */
    public function pusherAuth(Request $request)
    {
        // Auth data
        $authData = json_encode([
            'user_id' => Auth::user()->id,
            'user_info' => [
                'name' => Auth::user()->name
            ]
        ]);
        // check if user authorized
        if (Auth::check()) {

            return $this->pusher()->socket_auth($request['channel_name'], $request['socket_id'], $authData);

        }
        // if not authorized
        return new Response('Unauthorized', 401);
    }

    /**
     * Fetch message by id and return the message card
     * view as a response.
     *
     * @param int $id
     * @return array
     */
    public function fetchMessage($id){
        $attachment = $attachment_type = $attachment_title = null;
        $msg = Message::where('id',$id)->first();

        // If message has attachment
        if($msg->attachment){
            // Get attachment and attachment title
            $att = explode(',',$msg->attachment);
            $attachment       = $att[0];
            $attachment_title = $att[1];

            // determine the type of the attachment
            $ext = pathinfo($attachment, PATHINFO_EXTENSION);
            $attachment_type = in_array($ext,$this->getAllowedImages()) ? 'image' : 'file';
        }

        return [
            'id' => $msg->id,
            'from_id' => $msg->from_id,
            'to_id' => $msg->to_id,
            'message' => $msg->body,
            'attachment' => [$attachment, $attachment_title, $attachment_type],
            'time' => $msg->created_at->diffForHumans(),
            'fullTime' => $msg->created_at,
            'viewType' => ($msg->from_id == Auth::user()->id) ? 'sender' : 'default',
            'seen' => $msg->seen,
        ];
    }


    /**
     * Return a message card with the given data.
     *
     * @param array $data
     * @param string $viewType
     * @return void
     */
    public function messageCard($data, $viewType = null){
        $data['viewType'] = ($viewType) ? $viewType : $data['viewType'];
        return view('layouts.messageCard',$data)->render();
    }


    /**
     * Default fetch messages query between a Sender and Receiver.
     *
     * @param int $user_id
     * @return Collection
     */
    public function fetchMessagesQuery($user_id){
        return Message::where('from_id',Auth::user()->id)->where('to_id',$user_id)
                    ->orWhere('from_id',$user_id)->where('to_id',Auth::user()->id);
    }

    /**
     * create a new message to database
     *
     * @param array $data
     * @return void
     */
    public function newMessage($data){
        $message = new Message();
        $message->id = $data['id'];
        $message->type = $data['type'];
        $message->from_id = $data['from_id'];
        $message->to_id = $data['to_id'];
        $message->body = $data['body'];
        $message->attachment = $data['attachment'];
        $message->save();
    }



    /**
     * Returning the view of the app with the required data.
     *
     * @param int $id
     * @return void
     */
    public function index($id = null)
    {
        // get current route
        $route = (in_array(Request::route()->getName(), ['user', config('chatify.path')]))
            ? 'user'
            : Request::route()->getName();

        // prepare id
        return view('Chatify::pages.app', [
            'id' => ($id == null) ? 0 : $route . '_' . $id,
            'route' => $route,
            'messengerColor' => Auth::user()->messenger_color,
            'dark_mode' => Auth::user()->dark_mode < 1 ? 'light' : 'dark',
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
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
     * @param  \App\Models\Message  $message
     * @return \Illuminate\Http\Response
     */
    public function show(Message $message)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Message  $message
     * @return \Illuminate\Http\Response
     */
    public function edit(Message $message)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Message  $message
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Message $message)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Message  $message
     * @return \Illuminate\Http\Response
     */
    public function destroy(Message $message)
    {
        //
    }
}
