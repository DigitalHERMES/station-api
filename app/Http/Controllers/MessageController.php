<?php

namespace App\Http\Controllers;

use Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;
use App\Message;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    /**
     * Get all messages
     *  parameter: messages 
     *
     * @return Json
     */
    public function showAllMessages()
    {
        return response()->json(Message::all());
    }

    /**
     *  Get all messages by type
     *  parameter: 
     *
     * @return Json Messages
     */
    public function showAllMessagesByType($type)
    {
		if($type=='inbox'){
			return response()->json(Message::where('inbox', '=', true)->get());
		}
		if($type=='draft'){
			return response()->json(Message::where('draft', '=', true)->get());
		}
		else{
			return response()->json(Message::where('inbox', '!=', true)->where('draft', '!=', true)->get());
		}
    }

    /**
     * Get a message
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
		$request->orig = explode("\n", exec_cli("cat /etc/uucp/config|grep nodename|cut -f 2 -d \" \""))[0];

        if($message = Message::create($request->all())){
            if($request->pass){
                //TODO deal with input
                $command = 'echo "'. $request->text . '"| gpg -o - -c -t --cipher-algo AES256 --utf8-strings --batch --passphrase "' . $request->pass. '"  --yes -';
                $cryptout = "";
                if ($output = exec_cli($command) ){
                    $cryptout = $output; // redundant
                }
                else {
        			return response()->json(['message' => 'sendHMP: can\'t encrypt the message: ' ], 500);
                }
                $message->secure=true;
                $message->text=bin2hex($cryptout);
                $message->save();
            }

            //log
			Log::info('creating message ' . $message);
            //find the message in database
            // Assures to delete the working path
            Storage::deleteDirectory('tmp/'.$message->id);

            // Write message file
            if (! Storage::disk('local')->put('tmp/' . $message->id . '/hmp.json'  , $message)){
        		return response()->json(['message' => 'sendHMP Error: can\'t write message file'], 500);
            }

            // Has image?  
            if (Storage::disk('local')->exists('uploads/'.$message->id)) {
                // TODO testing purposes -  change for move
                // if (!$image = Storage::disk('local')->move('uploads/' . $id , 'tmp/'. $id . '/image' )){
                if (! Storage::disk('local')->copy('uploads/' . $message->id , 'tmp/' . $message->id . '/image' )){
        			return response()->json(['message' => 'Hermes send message Error: can\'t move image file'], 500);
                }
            }

            $path = Storage::disk('local')->path('tmp');
            $command  = 'tar cfz ' . $path . '/' . $message->id . '.hmp -C '.  $path . ' ' . $message->id  ;
            if ($output = exec_cli($command) ){
        		return response()->json(['message' => 'Hermes send message Error: cant move image file' . $output . $command], 500);
            }

            // Clean outbox destination and move the package
            if (! Storage::disk('local')->move('tmp/'.$message->id.'.hmp', env('HERMES_OUTBOX').'/'.$message->id.'.hmp')){
        		return response()->json(['message' => 'Hermes pack message Error: cant package the files' . $output . $command], 500);
            }
            //$message = @json_decode(json_encode($messagefile), true);
            Storage::disk('local')->deleteDirectory('tmp/'.$message->id);

            //work path
            $path = Storage::disk('local')->path(env('HERMES_OUTBOX') . '/'.$message->id.'.hmp');

            // UUCP -C Copy  (default) / -d create dirs
            if (Storage::disk('local')->exists(env('HERMES_OUTBOX').'/'.$message->id.'.hmp')) {
				//send message by uucp
                $command = 'uucp -r -j -C -d \'' .  $path . '\' \'' . $message->dest . '!~/' . $message->orig . '-' . $message->id . '.hmp\''; ;
                if(!$output = exec_cli_no($command)){
						return response()->json(['message' => 'Hermes sendMessage - Error on uucp:  ' . $output . ' - ' .$command], 500);
				}
				//setting no draft
				if (! $message->update([ 'draft' => false])){
					return response()->json(['message' => 'Hermes sendMessage - cant update no draft:  ' . $output ], 500);
				}

				Log::info('sent message ' . $message->id);
            }
            else{
        		return response()->json(['message' => 'Hermes send message Error: Cant find '.$message->id. '  HMP uucp'], 500);
            }
        }
        else{
        	return response()->json(['message' => 'Hermes pack message Error: can\'t create message in DB'], 500);
        }

        return response()->json(['message' => 'Hermes sendMessage: DONE', 'content' => $message], 200);
    }


    /**
     * updateMessage - update Hermes Message Pack (DEPRECATED)
     * parameter: id and http request
     *
     * @return Json
     */
    public function updateMessage($id, Request $request)
    {
        if($message = Message::findOrFail($id)){
            $message->update($request->all());
			Log::info('update message ' . id);
            return response()->json($user, 200);
        }
        else{
			Log::warning('update message cant find ' . $id);
        	return response()->json(['message' => 'cant find ' . $id], 404);
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
		Log::info('delete message ' . $id);
        return response()->json(['message' => 'Delete sucessfully message: ' . $id], 200);
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
        $id = explode('.', $id)[0];

        $message='';
        // Test for tmp dir, if doesnt exist, creates it
        if (! Storage::disk('local')->exists('inbox/tmp')){
            if(!Storage::disk('local')->makeDirectory('tmp')){
        		return response()->json(['message' => 'Hermes unpack inbox message Error: can\'t find or create tmp dir'], 500);
            }
        }
        // Test for HMP file and unpack it
         if (Storage::disk('local')->exists('inbox/'. $orig  . '-' . $id . '.hmp')){
            // Get path, unpack into tmp and read message data
            $path = Storage::disk('local')->path('');
            $command  = 'tar xvfz ' .  $path . 'inbox/' . $orig .'-' . $id  . '.hmp' . ' -C ' . $path . 'tmp/'  ;
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
        		return response()->json(['message' => 'Hermes unpack inbox message Error: cant find json file from unpacked message'], 500);
            }

            //create message on database, delete tar and hmp
            if(!$message = Message::create($message)){
        		return response()->json(['message' => 'Hermes unpack inbox message Error: cant create message on db'], 500);
            }

            // Move attached files
            if (Storage::disk('local')->exists('tmp/'.$id.'/image')){
                // Test and create download folder if it doesn't exists
                if (! Storage::disk('local')->exists('downloads')){
                    if(!Storage::disk('local')->makeDirectory('downloads')){
        				return response()->json(['message' => 'Hermes unpack inbox message Error: can\'t find or create downloads dir'], 500);
                    }
                }
                // move image
                if (Storage::disk('local')->copy('tmp/' . $id . '/image', 'downloads/' . $message['id']. '.image' )){
                }
                else{
        			return response()->json(['message' => 'Hermes unpack inbox message Error: can\'t move imagefile'], 500);
                }
                // TODO move audio and other files
            }

            if (Storage::disk('local')->exists('tmp/'.$id)){
                if (!Storage::disk('local')->deleteDirectory('tmp/' .  $id)){
        			return response()->json(['message' => 'Hermes unpack inbox message Error: can\'t delete tmp dir'], 500);
                }
                if (!Storage::disk('local')->delete('inbox/' . $orig . '-' . $message['id'] . '.hmp' )){
        			return response()->json(['message' => 'Hermes unpack inbox message Error: can\'t delete orig file'], 500);
                }
            }
            else{
        		return response()->json(['message' => 'Hermes unpack inbox message Error: can\'t create message on database'], 500);
            }
        }
        else {
        	return response()->json(['message' => 'Hermes unpack inbox message Error: can\'t find HMP'], 500);
        }
        Log::info('hermes.log', date('Y-m-d H:i:s' ) . 'unpack  '. $id  . ' - ' . $message .  ' from ' . $orig  );

        return response()->json(['message' => $message], 200);
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
        log::info('hide message ' . $id);
		return response()->json(['hide messag ' . $id . 'Sucessfully'], 200);
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
		return response()->json(['unhide' . $id . 'Sucessfully'], 200);
    }

    /**
     * unCrypt message 
     * parameter: message id, $request->pass
     * TODO ALL
     * @return Json
     */
    public function unCrypt($id, Request $request){
		if ($request->pass) {
        	if ($message = Message::find($id)){
				if ($message['secure'] ){
                	$crypt = hex2bin($message['text']);
            		if ( Storage::disk('local')->put('tmp/' . $message->id . '-uncrypt'  , $crypt)){
						$path = Storage::disk('local')->path('tmp') . '/' . $message->id . '-uncrypt';
						$command  = 'gpg -d --batch --passphrase "' .  $request->pass . '" --decrypt ' . $path  ;
						$output = exec_cli($command);

						Log::info('message unCrypt  '. $id  . ' - ' . $output );
						return response()->json(['text' => $output], 200);
					}
				}
				else{
					Log::warning('message unCrypt message is not secure '. $id  . ' - ' . $output );
        			return response()->json(['message' => 'HMP uncrypt error: message is not secured'], 500);
				}

			}
			else{
       			return response()->json(['message' => 'HMP uncrypt error: cant find message'], 500);
			}
		}
		else{
       			return response()->json(['message' => 'HMP uncrypt error: form pass is required'], 500);
		}
	}
}