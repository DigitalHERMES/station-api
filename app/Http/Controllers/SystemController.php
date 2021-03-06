<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;
use Illuminate\Http\Request;

use App\System;


function exec_cli($command = "ls -l")
{
    ob_start();
    system($command , $return_var);
    $output = ob_get_contents();
    ob_end_clean();

    //or die;
    /*if ($exploder==true){
            return (explode("\n", $output));
            }*/

    return ($output);
}

function exec_nodename(){

    $command = 'cat /etc/uucp/config|grep nodename|cut -f 2 -d " "';
    $output = exec_cli($command);
    $output = explode("\n", $output)[0];

    return $output;
}

class SystemController extends Controller
{

    public function getSysConfig()
    {
        return response(System::first(),200);
    }

    public function setSysConfig(Request $request)
    {
        if ($request->all()){
            //TODO
              if (System::select()->update($request->all())){
                return response()->json($request->all() , 200);
            }
            else {
                return response()->json('can\'t update', 500);
            }
        }
        else {
            return response()->json('Error, does not have request data', 500);
        }
    }


    /**
     * Get Name station from uucp
     *
     * @return string
     */
    public function  getSysNodeName()
    {
       return  response(json_encode(exec_nodename()),200);
    }

    /**
     * Get system status
     *
     * @return Table
     */
    public function getSysStatus()
    {
        $sysname = explode("\n", exec_cli("uname -n"))[0];
        $piduu = explode("\n", exec_cli("pgrep -x uuardopd"))[0];
        $pidmodem  = explode("\n", exec_cli("pgrep -x VARA"))[0];
        $piddb = explode("\n", exec_cli("pgrep -x mariadbd"))[0];
        $pidir = explode("\n", exec_cli("pgrep -x iredadmin"))[0];
        $pidpf = explode("\n", exec_cli("pgrep -x postfix"))[0];
        $pidtst = explode("\n", exec_cli("echo test"))[0];
        $ip = explode("\n", exec_cli('/sbin/ifconfig | sed -En \'s/127.0.0.1//;s/.*inet (addr:)?(([0-9]*\.){3}[0-9]*).*/\2/p\''))[0];
        // $ip = exec_cli('hostname -I');// doesnt work on arch
        $memory = explode(" ", exec_cli("free | grep Mem|cut -f 8,13,19,25,31,37 -d \" \""));
        $phpmemory = ( ! function_exists('memory_get_usage')) ? '0' : round(memory_get_usage()/1024/1024, 2).'MB';
        $status = [
            'status' => $piduu && $pidmodem && $pidir && $pidpf,
            'name' => $sysname,
            'nodename' => exec_nodename(),
            'piduu' => $piduu?$piduu:false,
            'piddb' => $piddb?$piddb:false,
            'pidmodem' => $pidmodem?$pidmodem:false,
            'pidtst' => $pidtst,
            'ipaddress' => $ip,
            'memory' => $memory,
            'phpmemory' => $phpmemory
        ];
        return response($status, 200);
    }

    /**
     * Get files from $path
     *
     * @return Table
     */
    public function getFiles($path)
    {
        if (!$path ){
            $command = "ls -la /etc/uucp";
        }
        $command = "ls -la " . $path;
        $output = exec_cli($command);
        $output =  explode("\n ", $output);
        return  $output;
    }

    /**
     * Get info if system is running
     *
     * @return Boolean
     */
    public function isRunning(){
        //TODO
        exec("pgrep -x uuardopd", $piduu);
        exec("pgrep -x ardop", $pidar);
        if(empty($piduu) || empty($pidar)){
            return false; //we have a problem!;
        } else {
            return true; //system is working!;
        }
    }

    /**
     * Get info if system is running TODO parametro grupo
     *
     * @return table
     */
    public function queueErase(){
        //$command = "sudo uustat -u www-data -K";
        $command = "uustat -u www-data -K";
        //TODO ? repeated?
        //$command = "sudo uustat -u uucp -K";
        $output = exec_cli($command);
        //$command = "sudo uustat -u root -K";
        $command = "uustat -u root -K";
        $output2 = exec_cli($command);
        //TODO
        return [$output,$output2] ;
    }

    /**
     * Get all Stations from uucp
     *
     * @return stations
     */
    public function getSysStations(){
        $command = "egrep -v '^\s*#' /etc/uucp/sys | grep system | cut -f 2 -d \" \"";
        $output = exec_cli($command);
        $command2 = "egrep -v '^\s*#' /etc/uucp/sys | grep alias | cut -f 2 -d \" \"";
        if (!$output2 = exec_cli($command2)){
            $output2 = null;
        }

        $command3 = "egrep -v '^\s*#' /etc/uucp/sys | grep address | cut -f 2 -d \" \"";
        $output3 = exec_cli($command3);
        $sysnames = explode("\n", $output);
        $sysnames2 = explode("\n", $output2);
        $sysnames3 = explode("\n", $output3);
        $sysnameslist=[];

        for ($i = "0" ; $i < count($sysnames); $i++) {
            if(!empty($sysnames[$i])) {
                $sysnameslist[]  =  [
                    'id' => $i,
                    'name' => $sysnames[$i],
                    'alias' => $sysnames2[$i],
                    'location' => $sysnames3[$i]
                ];
            }
        }

        return $sysnameslist;
    }

    /**
     * Get transmission spool 
     *
     * @return Json
     */
    public function sysGetSpoolList(){
        $command = "uustat -a";
        $output=exec_cli($command) or die;
        $output = explode("\n", $output);
        $spool=[];

        for ($i = "0" ; $i < count($output); $i++) {
            if(!empty($output[$i])) {
                $fields = explode(" ", $output[$i]);
                $spool[]  =  [
                    //  '#' => $i,
                    'id' => $fields[0],
                    'dest' => $fields[1],
                    'user' => $fields[2],
                    'date' => $fields[3],
                    'time' => $fields[4],
                    'desc' => $fields[5] . ' ' .  $fields[6] . ' ' . $fields[7] . ' ' . 
                              $fields[8] . ' '. $fields[9] . ' ' . $fields[10] 

                ];
            }
        }

        return response($spool, 200);
    }

    //DONE in FileController
    public function fileLoad(){
        $command = "uustat -a| cut -f 2,7,8,9 -d \" \" | sed \"s/\/var\/www\/html\/uploads\///\"";
            $output = exec_cli($command);
            //TODO return true or false?
            return $output;
    }

    public function uucpJobsKill(){

        $command = 'sudo killall -9 uucico && sudo killall -9 uuport'; //TODO check uuport
        $output=exec_cli($command) or die;
        return $output;
    }

    function uucpJobList(){
        //TODO fix sed
        $command = 'uustat -a| cut -f 2,7,8,9 -d \" \" | sed \"s/\/var\/www\/html\/uploads\///\"';
        $output = exec_cli($command);
        echo $output;
    }

    //TODO convert to eloquent/flysystem
    // Open a directory, and read its contents
    public function systemDirList(){

        if (is_dir($cfg['path_files'])){
            if ($dh = opendir($cfg['path_files'])){
                while (($file = readdir($dh)) !== false){
                    if ($file == '.' || $file == '..') {
                        continue;
                    }

                    //TODO to array
                    echo "Arquivo:" . $file . "<br />";
                }
                closedir($dh);
            }
        }
    }

    //port script restart_system.sh
    public function systemRestart() {
        $command = "sudo systemctl stop uuardopd";
        $output0 = exec_cli($command);

        $command = "sudo systemctl stop ardop";
        $output1 = exec_cli($command);

        //TODO sleep php
        $command = "sleep 1";
        $output2 = exec_cli($command);

        $command = "sudo systemctl start ardop";
        $output3 = exec_cli($command);

        $command = "sudo systemctl start uuardopd" ;
        $output4 = exec_cli($command);

        //TODO
        return json_encode([$output0,$output1,$output2,$output3,$output4,$output5]);
    }

    function sysDoShutdown(){
        $command = "sudo halt";
        exec_cli($command);
    }

    function sysGetLog(){
        $command = "uulog|tail -50";
        $output=exec_cli($command);
        $output = explode("\n",$output);
        return $output;
    }
}


//TODO
//alias.sh bash contents
/*
 * oline=$(grep -n $1 /etc/uucp/sys|cut -d ':' -f 1)
 *  linePlus=$((line+1))
 *  #echo $line
 *  name=$(head -$linePlus /etc/uucp/sys|tail -1|cut -d ' ' -f 2)
 *  echo -n $name
 */


