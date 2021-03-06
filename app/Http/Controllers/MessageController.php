<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;
use App\Message;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function showAllMessages()
    {
        return response()->json(Message::all());
    }

    /**
     * Get message
     *  parameter: message id
     *
     * @return Json
     */
    public function showOneMessage($id)
    {
        return response()->json(Message::find($id));
    }

    /**
     * SendHMP - Send Hermes Message Pack
     * parameter: http request
     *
     * @return Json
     */
    public function sendHMP(Request $request)
    {
        $request->inbox=false;

        if($message = Message::create($request->all())){
            if($request->pass){
                //TODO deal with input
                $command = 'echo "'. $request->text . '"| gpg -o - -c -t --cipher-algo AES256 --utf8-strings --batch --passphrase "' . $request->pass. '"  --yes -';
                $cryptout = "";
                if ($output = exec_cli($command) ){
                    $cryptout = $output; // redundant
                }
                else {
                    return response('Hermes send message Error: can\'t encrypt the message: ' . $output . $command);
                }
                $message->secure=true;
                $message->text=bin2hex($cryptout);
                $message->save();
            }

            //log
            Storage::append('hermes.log', date('Y-m-d H:i:s' ) . 'create message . ' . $message  );
            //find the message in database
            // Assures to delete the working path
            Storage::deleteDirectory('tmp/'.$message->id);

            // Write message file
            if (! Storage::disk('local')->put('tmp/' . $message->id . '/hmp.json'  , $message)){
                return response('Hermes pack message Error: can\'t write message file',500);
            }

            // Has image?  - TODO change file for image in DB
            if (Storage::disk('local')->exists('uploads/'.$message->id)) {
                // TODO testing purposes -  change for move
                // if (!$image = Storage::disk('local')->move('uploads/' . $id , 'tmp/'. $id . '/image' )){
                if (! Storage::disk('local')->copy('uploads/' . $message->id , 'tmp/' . $message->id . '/image' )){
                    return response('Hermes send message Error:  can\'t move image file',500);
                }
            }

            $path = Storage::disk('local')->path('tmp');
            $command  = 'tar cfz ' . $path . '/' . $message->id . '.hmp -C '.  $path . ' ' . $message->id  ;
            if ($output = exec_cli($command) ){
                return response('Hermes send message Error: can\'t package the files: ' . $output . $command);
            }

            // Clean outbox destination and move the package
            Storage::disk('local')->delete('outbox/'.$message->id.'.hmp');
            if (! Storage::disk('local')->move('tmp/'.$message->id.'.hmp', 'outbox/'.$message->id.'.hmp')){
                return response('Hermes pack message Error: can\'t package the files: ' . $output . $command);
            }
            //$message = @json_decode(json_encode($messagefile), true);
            Storage::disk('local')->deleteDirectory('tmp/'.$message->id);

            //work path
            $path = Storage::disk('local')->path('outbox/'.$message->id.'.hmp');

            /* // TODO test for draft
            if($message['draft']){
            }*/

            // UUCP -C Copy  (default) / -d create dirs
            if (Storage::disk('local')->exists('outbox/'.$message->id.'.hmp')) {
                $command = 'uucp -C -d \'' .  $path . '\' ' . $message->dest . '!~/' . $message->orig . '-' . $message->id  ;
                if ($output = exec_cli($command) ){
                    return response('Hermes sendMessage: Error on uucp: ' . $output . $command);
                }
                $message['draft']=false;
                //$message->update($message);

                Storage::append('hermes.log', date('Y-m-d H:i:s' ) . 'sent message . '. $message->id  . ' - ' . $message   );
                //$output = exec_cli($command);
                //TODO test output for error
            }
            else{
                return response('Hermes send message Error: Cant find '.$message->id. '  HMP uucp',500);
            }
        }
        else{
            return response('Hermes pack message Error: can\'t create message in DB',500);
        }

        return response(['Hermes sendMessage: DONE', $command,'output cli: '. $output, $message],200);
    }

     /**
     * createMessage - createMessage on database
     * parameter: http request
     *
     * @return Json
     */
    public function createMessage(Request $request)
    {
        $request->inbox=false;
        $message = Message::create($request->all());
        Storage::append('hermes.log', date('Y-m-d H:i:s' ) . 'create message . ' . $message  );
        return response()->json($message, 201);
    }

    /**
     * updateMessage - Send Hermes Message Pack
     * parameter: id and http request
     *
     * @return Json
     */
    public function updateMessage($id, Request $request)
    {
        if($message = Message::findOrFail($id)){
            $message->update($request->all());
            Storage::append('hermes.log', date('Y-m-d H:i:s' ) . 'update message . '. $id  .  ' -> ' . $remote  );
            return response()->json($user, 200);
        }
        else{
            return response()->json('cant find ' . $user, 404);
        }
    }

    /**
     * deleteMessage - deleteMessage
     * parameter: message id
     * TODO deal with files
     * @return Json
     */
    public function deleteMessage($id)
    {
        Message::findOrFail($id)->delete();
        Storage::append('hermes.log', date('Y-m-d H:i:s' ) . 'delete message . '. $id  );
        return response('Deleted Successfully', 200);
    }

    /**
     * unpackInboxMessage - Unpack Hermes Message Pack
     * parameter: id and http request
     *
     * @return Json
     */
    public function unpackInboxMessage($arg){
        $arg = explode('-', $arg);
        $orig = $arg[0];
        $id = $arg[1];

        $message='';
        // Test for tmp dir, if doesnt exist, creates it
        if (! Storage::disk('local')->exists('inbox/tmp')){
            if(!Storage::disk('local')->makeDirectory('tmp')){
                return response('Hermes unpack inbox message Error: can\'t find or create tmp dir');
            }
        }
        // Test for HMP file and unpack it
         if (Storage::disk('local')->exists('inbox/'. $orig  . '-' . $id )){
            // Get path, unpack into tmp and read message data
            $path = Storage::disk('local')->path('');
            $command  = 'tar xvfz ' .  $path . 'inbox/' . $orig .'-' . $id  .  ' -C ' . $path . 'tmp/'  ;
            $output = exec_cli($command);
            $files[] = explode(' ', $output);

            // Test for HMP: hermes message package, create record on messages database
            if (Storage::disk('local')->exists('tmp/'.$id.'/hmp.json')){
                $messagefile = json_decode(Storage::disk('local')->get('tmp/'. $id . '/hmp.json'));
                $message = @json_decode(json_encode($messagefile), true);
                $message['id'] = null;
                $message['inbox'] = true;
            }
            else {
                return response('Hermes unpack inbox message Error: can\'t find data file from unpacked message');
            }

            //create message on database, delete tar and hmp
            if(!$message = Message::create($message)){
                return response('Hermes unpack inbox message Error: can\'t create message on db' , 500);
            }

            // Move attached files
            if (Storage::disk('local')->exists('tmp/'.$id.'/image')){
                // Test and create download folder if it doesn't exists
                if (! Storage::disk('local')->exists('downloads')){
                    if(!Storage::disk('local')->makeDirectory('downloads')){
                        return response('Hermes unpack inbox message Error: can\'t find or create downloads dir');
                    }
                }
                // move image
                if (Storage::disk('local')->copy('tmp/' . $id . '/image', 'downloads/' . $message['id']. '.image' )){
                }
                else{
                    return response('Hermes unpack inbox message Error: can\'t copy file image from unpacked message');
                }
                // TODO move audio and other files
            }


            if (Storage::disk('local')->exists('tmp/'.$id)){
                if (!Storage::disk('local')->deleteDirectory('tmp/' .  $id)){
                    return response('Hermes unpack inbox message Error: can\'t delete tmp dir');
                }
                if (!Storage::disk('local')->delete('inbox/' . $orig . '-' . $message['id'] )){
                    return response('Hermes unpack inbox message Error: can\'t delete orig file');
                }
            }
            else{
                return response('Hermes unpack inbox message Error: can\'t create message on database', 500);
            }
        }
        else {
            return response('Hermes unpack inbox message Error: can\'t find HMP', 500);
        }
        Storage::append('hermes.log', date('Y-m-d H:i:s' ) . 'unpack  '. $id  . ' - ' . $message .  ' from ' . $orig  );
        return response( $message,200);
    }

    /**
     * showAllInboxMessages
     *
     * @return Json
     */
    public function showAllInboxMessages()
    {
        $files = \Storage::allFiles('inbox');
        $file = [];

        /*$filtered_files = array_filter($files, function($str){
            return strpos($str, 'hmp') === 0;
        });*/

        $files_out = [];
        for ($i = '0' ; $i < count($files); $i++) {
            $file = explode('inbox/', $files[$i]);

            if(!empty($files[$i])) {
                $files_out[] = $file[1];
            }

        }
        //var_dump($files_out);
        return response()->json($files_out);
    }

    /**
     * showOneInboxMessage
     * parameter: message id
     * @return Json
     */
    public function showOneInboxMessage($id)
    {
        $file = \Storage::get('inbox/' . $id);
        $output = explode('}', $file)[0];
        $output = $output . '}';
        $output = json_decode($output);
        return response()->json($output);
    }

   /**
     * showOneInboxMessageImage
     * parameter: message id
     * TODO deal with files
     * @return Json
     */
    public function showOneInboxMessageImage($id)
    {
        $file = \Storage::get('inbox/' . $id);
        $output = explode('}', $file);
        return response($output[1],200);
    }

    /**
     * hideInboxMessage
     * parameter: message id
     * TODO deal with files
     * @return Json
     */
    public function hideInboxMessage($id)
    {
        \Storage::move('inbox/' . $id, 'inbox/.' . $id);
        return response('hide ' . $id . ' Successfully', 200);
    }

    /**
     * unhideInboxMessage
     * parameter: message id
     *
     * @return Json
     */
    public function unhideInboxMessage($id)
    {
        \Storage::move('inbox/.' . $id, 'inbox/' . $id);
        return response('unhide ' . $id . ' Successfully', 200);
    }

    /**
     * execDecrypt
     * parameter: message id
     * TODO ALL
     * @return Json
     */
    public function execDecrypt($id){
        //TODO path
        $path = $_POST['path'];
        $dec_subdir=dirname($path)."/dec/";
        mkdir($dec_subdir, 0777, TRUE);
        $outfile=$dec_subdir.basename($path,".gpg");
        //TODO
        $command = "decrypt.sh ".$path." ".$outfile." ".$_POST['password'];
        $output = exec_cli($command);

        if ($return_var == 0){
            //TODO this path can't be here
            $prefix = '/var/www/html/';

            if (substr($outfile, 0, strlen($prefix)) == $prefix) {
                $str = substr($outfile, strlen($prefix));
            }
            //correct password
            //TODO  // basename($str);
            return $str;
        } else {
            // wrong password
            unlink($outfile);
            return FALSE;
        }
    }
}