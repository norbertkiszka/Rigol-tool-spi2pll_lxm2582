<?php

// Tool to generate C header file with registers
// and compile tool to set those in LMX2582 via SPI bus.
// It uses data exported from TICSPro by Texas Instruments.
// 
// NOTE: tested only with TICSPro1.7.6.2_10-OCT-2023.exe
// 
// Copyright (C) 2025 Norbert Kiszka
// 
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; version 2
// of the License.
// 
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// 
// See the GNU General Public License for more details.
// 
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

// SPDX-License-Identifier: GPL-2.0

define('OUTPUT_DIR_FOR_HEADERS', getcwd() . '/generated');
define('OUTPUT_DIR_FOR_PROGRAM', getcwd() . '/compiled');

define('C_COMPILER_DEFAULT', '/usr/lib/android-ndk/toolchains/llvm/prebuilt/linux-x86_64/bin/aarch64-linux-android24-clang');
define('C_COMPILER_ARGS_DEFAULT', '--sysroot=/usr/lib/android-ndk/toolchains/llvm/prebuilt/linux-x86_64/sysroot -Wall --extra-warnings -pedantic -Werror');
//define('C_COMPILER_DEFAULT', '/usr/bin/gcc');
//define('C_COMPILER_ARGS_DEFAULT', '-Wall -Werror');

define('C_COMPILER_OUTPUT_FILE_PREFIX', 'spi2pll_lxm2582_'); // Rigol misspelled lxm2582 with lmx2582

define('MINIMAL_PHP_VERSION_REQUIRED', '5.0.0'); // file_put_contents() requires 5.x.x at least. Everything else: 4.1.x.

if (!function_exists('version_compare') || version_compare(PHP_VERSION, MINIMAL_PHP_VERSION_REQUIRED) <= 0)
	die('Your PHP is too old for this. Use ' . MINIMAL_PHP_VERSION_REQUIRED . " or newer.\n");

function errHandle($errNo, $errStr, $errFile, $errLine)
{
	echo "$errStr in $errFile on line $errLine\n";
	echo "Error number: $errNo\n";
	die("Forbidden PHP error\n");
}

set_error_handler('errHandle');
error_reporting(-1);

if(!isset($argc) || !isset($argv) || !is_int($argc) || !is_array($argv) || !isset($argv[0]) || !is_string($argv[0]))
	die("This script requires php-cli to run.\n");

if($argc != 2 && $argc != 4 || $argc == 2 && ($argv[1] == '-h' || $argv[1] == '--help'))
{
	if($argc != 2 || $argc == 2 && !($argv[1] == '-h' || $argv[1] == '--help'))
		echo "Bad usage...\n";
	echo "Usage:\n";
	echo "\t" . $argv[0] . " file_from_tics_pro_with_exported_registers\n";
	echo "\t" . $argv[0] . " path_to_the_compiler compiler_args file_from_tics_pro_with_exported_registers\n\n";
	echo 'Output dir: ' . OUTPUT_DIR_FOR_PROGRAM . "\n";
	die();
}

function parse_file_and_generate_array_with_registers_data($txtfile)
{
	$source_data = file_get_contents($txtfile);

	if(!is_string($source_data))
		die("Error occured at reading file $txtfile.\n");

	if(!strlen(trim($source_data)))
		die("File $txtfile appears to be empty.\n");

	$lines = explode("\n", $source_data);

	if(count($lines) != 47)
		die("Bad data: input file should have 46 lines (46 registers) and empty new line at the end.\n");

	$o = "char d[] = {\n";

	$last = count($lines) - 1;
	$nexttolast = $last - 1;

	$registers = [70, 69, 68, 64, 62, 61, 59, 48, 47, 46, 45, 44, 43, 42, 41, 40, 39, 38, 37, 36, 35, 34, 33, 32, 31, 30, 29, 28, 25, 24, 23, 22, 20, 19, 14, 13, 12, 11, 10, 9, 8, 7, 4, 2, 1, 0];

	foreach($lines as $k => $line)
	{
		$line = trim($line);
		
		if($k == $last)
		{
			if($line !== '')
				die("Bad data: last line is not empty.\n");
			break;
		}
		
		$real_line_num = $k + 1;
		
		$parts = explode('	', $line);
		
		if(!is_array($parts) || count($parts) !== 2)
			die("Bad data in line $real_line_num or some other error...\n");
		
		$regname = $parts[0];
		$reg = $parts[1];
		
		if
		(
			!is_string($regname)
			|| strlen($regname) < 2
			|| strlen($regname) > 3
			|| $regname[0] !== 'R'
		)
			die("Register name in line $real_line_num appears to be bad...\n");
		
		$regnum = substr($regname, 1);
			
		if
		(
			!is_string($regnum)
			|| strlen($regnum) < 1
			|| strlen($regnum) > 2
			|| !is_numeric($regnum)
		)
			die("Bad register number in line $real_line_num or some other error...\n");
		
		if(!isset($registers[$k]))
			die("Too much registers or \$registers array is bad...\n");
		
		if($regnum != $registers[$k])
			die("Expected register number {$registers[$k]} in line $real_line_num, but there was $regnum.\n");
		
		if(!is_string($reg))
			die("Bad register data or explode() error with register data from line $real_line_num.\n");
		
		if(strlen($reg) !== 8)
			die("Bad register data for $regname (line $real_line_num). It should have 8 chars.\n");
		
		if(substr($reg, 0, 2) !== '0x')
			die("Register data for $regname (line $real_line_num) should be hexadecimal and starts with 0x\n");
		
		$reg = substr($reg, 2);
		
		if(!is_string($reg) || strlen($reg) != 6)
			die("Can't happen... Line: $real_line_num\n");
		
		if(!ctype_xdigit($reg))
			die("Register data for $regname (line $real_line_num) doesn't look like a hex number\n");
		
		$b0 = substr($reg, 0, 2);
		$b1 = substr($reg, 2, 2);
		$b2 = substr($reg, 4, 2);
		
		$o .= "\t0x$b0, 0x$b1, 0x$b2";
		
		if($k != $nexttolast)
			$o .= ',';
		
		$o .= "\t// $regname\n";
	}
	
	return $o . '};' . "\n";
}

if(file_exists(OUTPUT_DIR_FOR_HEADERS) && !is_dir(OUTPUT_DIR_FOR_HEADERS))
	die(OUTPUT_DIR_FOR_HEADERS . " exists but it's not a directory\n");

if(!file_exists(OUTPUT_DIR_FOR_HEADERS))
	if(!mkdir(OUTPUT_DIR_FOR_HEADERS, 0700, true))
		die('mkdir ' . OUTPUT_DIR_FOR_PROGRAM . " error.\n");

if(file_exists(OUTPUT_DIR_FOR_PROGRAM) && !is_dir(OUTPUT_DIR_FOR_PROGRAM))
	die(OUTPUT_DIR_FOR_PROGRAM . " exists but it's not a directory\n");

if(!file_exists(OUTPUT_DIR_FOR_PROGRAM))
	if(!mkdir(OUTPUT_DIR_FOR_PROGRAM, 0700, true))
		die('mkdir ' . OUTPUT_DIR_FOR_PROGRAM . " error.\n");

if($argc == 2)
{
	define('TICS_TXT_FILE', $argv[1]);
	define('OUTPUT_PROGRAM', OUTPUT_DIR_FOR_PROGRAM . '/' . C_COMPILER_OUTPUT_FILE_PREFIX . basename($argv[1], '.txt'));
	define('OUTPUT_HEADER', OUTPUT_DIR_FOR_HEADERS . '/' . basename($argv[1]) . '.h');
	define('COMPILER', C_COMPILER_DEFAULT);
	define('COMPILER_ARGS', C_COMPILER_ARGS_DEFAULT);
}
elseif($argc == 4)
{
	define('TICS_TXT_FILE', $argv[3]);
	define('OUTPUT_PROGRAM', OUTPUT_DIR_FOR_PROGRAM . '/' . C_COMPILER_OUTPUT_FILE_PREFIX . basename($argv[3], '.txt'));
	define('OUTPUT_HEADER', OUTPUT_DIR_FOR_HEADERS . '/' . basename($argv[3]) . '.h');
	define('COMPILER', $argv[1]);
	define('COMPILER_ARGS', $argv[2]);
}
else
	die("Can't happen...\n");

$generated_header_contents = '#include <stdio.h>
#include <stdlib.h>
#include <errno.h>
#include <fcntl.h>
#include <unistd.h>
#include <string.h>

extern int errno;

inline static void do_first(void)
{
	printf("Compiled at %s %s\n", __DATE__, __TIME__);
	printf("LMX2582 registers source: ' . realpath(TICS_TXT_FILE) . '\n");
}

';

$generated_header_contents .= parse_file_and_generate_array_with_registers_data(TICS_TXT_FILE);

if(file_put_contents(OUTPUT_HEADER, $generated_header_contents) !== strlen($generated_header_contents))
	die('Header ' . OUTPUT_HEADER . " write error or something else happened.\n");

if(!is_executable(COMPILER))
	die('Compiler '. COMPILER . ' not found or is not executable.');

$result_code = null;
if(system(COMPILER . ' ' . COMPILER_ARGS . ' --include=' . OUTPUT_HEADER . ' src/spi2pll_lxm2582.c -o ' . OUTPUT_PROGRAM, $result_code) === false || $result_code !== 0)
	die("Compiler error...\n");
