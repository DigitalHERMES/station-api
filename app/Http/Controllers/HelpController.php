<?php

namespace App\Http\Controllers;

class HelpController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public function showHelpMain()
    {
        $manual = [ 'Rhizo Hermes API' => 'V0.0.3 -  default page, help',
            'License and Copyrights' => 'gplv2, some rights reserved',
            '-----------------------' => '----------------------------------------',
            '/help' => 'TODO manual',
            '/sys/help' => 'TODO manual',
            '--------DATA---------------' => '----------------------------------------',
            '/user/' => 'user, get, delete ',

        ];
    return $manual;
    }


    public function showHelpSys()
    {
        $manual = [ 'Rhizo Hermes API' => 'V0.0.3 -  Sys page, help',
            'License and Copyrights' => 'gplv2, some rights reserved',
            '--------------------------' => '----------------------------------------',
            'sys/ls' => 'showFiles',
            'sys/help' => 'showHelpSys()',
            'sys/getnodename' => 'getnodename()',
            'sys/getstations' => 'TODO mockup getStations()',
            'sys/isrunning' => 'isrunning()',
            'sys/erasequeue' => 'TODO not running now',
            'sys/systems' => 'TODO get_systems()',
            'sys/spoollist' => 'TODO exec_get_spool_list()',
            'sys/kill_job' => 'TODO exec_get_spool_list()',
            'sys/decrypt' => 'TODO decrypt ',
            'sys/reboot' => 'TODO reboot',
            'sys/shutdown()' => 'TODO shutdown ',
            'sys/viewlog()' => 'TODO viewlog',
            'sys/listfiles()' => 'TODO listfiles',
            'sys/viewjob()' => 'TODO viewjob',
        ];
    return $manual;
    }

    public function showHelpUser()
    {
        $manual = [ 'Rhizo Hermes API' => 'V0.0.3 -  Sys page, help',
            'License and Copyrights' => 'gplv2, some rights reserved',
            '----------------------' => '----------------------------------------',
            'user POST' => 'UserController@create',
            'user/{id} GET' => 'UserController@showOneUser',
            'user/{id} PUT' => 'UserController@update',
            'user/{id} DELETE' => 'UserController@delete',
            'users GET' => 'UserController@showAll',
        ];
    return $manual;
    }

    public function showHelpMessage()
    {
        $manual = [ 'Rhizo Hermes API' => 'V0.0.3 -  Sys page, help',
            'License and Copyrights' => 'gplv2, some rights reserved',
            '----------------------' => '----------------------------------------',
            'message POST' => 'MessageController@create',
            'message/{id} GET' => 'MessageController@showOneUser',
            'message/{id} PUT' => 'MessageController@update',
            'message/{id} DELETE' => 'MessageController@delete',
            'messages GET' => 'MessageController@showAll',
        ];
    return $manual;
    }

}

