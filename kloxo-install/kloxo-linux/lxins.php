<?php
//    Kloxo, Hosting Control Panel
//
//    Copyright (C) 2000-2009	LxLabs
//    Copyright (C) 2009-2010	LxCenter
//
//    This program is free software: you can redistribute it and/or modify
//    it under the terms of the GNU Affero General Public License as
//    published by the Free Software Foundation, either version 3 of the
//    License, or (at your option) any later version.
//
//    This program is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU Affero General Public License for more details.
//
//    You should have received a copy of the GNU Affero General Public License
//    along with this program.  If not, see <http://www.gnu.org/licenses/>.
//
include_once "../install_common.php";

function lxins_main()
{
	global $argv, $downloadserver;
	$opt = parse_opt($argv);
	$dir_name=dirname(__FILE__);
	$installtype = $opt['install-type'];
	$dbroot = isset($opt['db-rootuser'])? $opt['db-rootuser']: "root";
	$dbpass = isset($opt['db-rootpassword'])? $opt['db-rootpassword']: "";
	$osversion = find_os_version();
	$arch = `arch`;
	$arch = trim($arch);
	
	//--- create temporal flags for install
	system("mkdir -p /var/cache/kloxo/");
	system("echo 1 > /var/cache/kloxo/kloxo-install-firsttime.flg");

	if (!char_search_beg($osversion, "centos") && !char_search_beg($osversion, "rhel")) {
		print("Kloxo is only supported on CentOS 5 and RHEL 5\n");
		exit;
	}

	if(file_exists("/usr/local/lxlabs/kloxo")) {
		// Ask Reinstall
		if (get_yes_no("Kloxo seems already installed do you wish to continue?") == 'n') {
			print("Installation Aborted.\n");
			exit;
		}
	} else {
		// Ask License
		if (get_yes_no("Kloxo is using AGPL-V3.0 License, do you agree with the terms?") == 'n') {
			print("You did not agree to the AGPL-V3.0 license terms.\n");
			print("Installation aborted.\n\n");
			exit;
		} else {
			print("Installing Kloxo = YES\n\n");
		}
	}
	// Ask for InstallApp
	print("InstallApp: PHP Applications like PHPBB, WordPress, Joomla etc\n");
	print("When you choose Yes, be aware of downloading about 350Mb of data!\n");
	if(get_yes_no("Do you want to install the InstallAPP sotfware?") == 'n') {
		print("Installing InstallApp = NO\n");
		print("You can install it later with /script/installapp-update\n\n");
		$installappinst = false;
		//--- temporal flag for no install InstallApp
		system("echo 1 > /var/cache/kloxo/kloxo-install-disableinstallapp.flg");
	} else {
		print("Installing InstallApp = YES\n\n");
		$installappinst = true;
	}

	print("Adding System users and groups (nouser, nogroup and lxlabs, lxlabs)\n");
	system("groupadd nogroup");
	system("useradd nouser -g nogroup -s '/sbin/nologin'");
	system("groupadd lxlabs");
	system("useradd lxlabs -g lxlabs -s '/sbin/nologin'");

	print("Installing LxCenter yum repository for updates\n");
	install_yum_repo($osversion);

	$packages = array("sendmail", "sendmail-cf", "sendmail-doc", "sendmail-devel", "exim", "vsftpd", "postfix", "vpopmail", "qmail", "lxphp", "lxzend", "pure-ftpd", "imap");
	$list = implode(" ", $packages);
	print("Removing packages $list...\n");
	foreach ($packages as $package) {
		exec("rpm -e --nodeps $package > /dev/null 2>&1");
	}

	$packages = array("php-mbstring", "php-mysql", "which", "gcc-c++", "php-imap", "php-pear", "php-devel", "lxlighttpd", "httpd", "mod_ssl", "zip", "unzip", "lxphp", "lxzend", "mysql", "mysql-server", "curl","autoconf","automake","libtool", "bogofilter", "gcc", "cpp", "openssl", "pure-ftpd", "yum-protectbase");
	$list = implode(" ", $packages);

	while (true) {
		print("Installing packages $list...\n");
		system("PATH=\$PATH:/usr/sbin yum -y install $list", $return_value);
		if (file_exists("/usr/local/lxlabs/ext/php/php")) {
			break;
		} else {
			print("Yum Gave Error... Trying Again...\n");
			if (get_yes_no("Try again?") == 'n') {
				print("- BREAK: fix the problem and install again\n");			
				break;
			}
		}
	}

	print("Prepare installation directory\n");
	
	system("mkdir -p /usr/local/lxlabs/kloxo");

	if (file_exists("../../kloxo-current.zip")) {
		//--- that mean install with local copy
		@ unlink("/usr/local/lxlabs/kloxo/kloxo-current.zip");
		print("Local copying Kloxo release\n");
		passthru("mkdir -p /var/cache/kloxo");
		passthru("cp -rf ../../kloxo-current.zip /usr/local/lxlabs/kloxo");

		// the first step - remove 
		passthru("rm -f /var/cache/kloxo/kloxo-thirdparty*.zip");
		passthru("rm -f /var/cache/kloxo/lxawstats*.tar.gz");
		passthru("rm -f /var/cache/kloxo/lxwebmail*.tar.gz");
		passthru("rm -f /var/cache/kloxo/kloxophpsixfour*.tar.gz");
		passthru("rm -f /var/cache/kloxo/kloxophp*.tar.gz");
		passthru("rm -f /var/cache/kloxo/*-version");
		// the second step - copy from packer making if exist
		passthru("cp -rf ../../kloxo-thirdparty*.zip /var/cache/kloxo");
		passthru("cp -rf ../../lxawstats*.tar.gz /var/cache/kloxo");
		passthru("cp -rf ../../lxwebmail*.tar.gz /var/cache/kloxo");
		passthru("cp -rf ../../kloxo-thirdparty-version /var/cache/kloxo");
		passthru("cp -rf ../../lxawstats-version /var/cache/kloxo");
		passthru("cp -rf ../../lxwebmail-version /var/cache/kloxo"); 
//		if ( os_is_arch_sixfour() ) {
		if (file_exists("/usr/lib64")) {
			if (!is_link("/usr/lib/kloxophp")) {
				passthru("rm -rf /usr/lib/kloxophp");
			}
			passthru("cp -rf ../../kloxophpsixfour*.tar.gz /var/cache/kloxo");
			passthru("cp -rf ../../kloxophpsixfour-version /var/cache/kloxo");
			passthru("mkdir -p /usr/lib64/kloxophp");
			passthru("ln -s /usr/lib64/kloxophp /usr/lib/kloxophp");
			passthru("mkdir -p /usr/lib64/php");
			passthru("ln -s /usr/lib64/php /usr/lib/php");
			passthru("mkdir -p /usr/lib64/httpd");
			passthru("ln -s /usr/lib64/httpd /usr/lib/httpd");
			passthru("mkdir -p /usr/lib64/lighttpd");
			passthru("ln -s /usr/lib64/lighttpd /usr/lib/lighttpd");
		}
		else {
			//--- use this trick because lazy to make code for version check
			passthru("rename ../../kloxophpsixfour ../../_kloxophpsixfour ../../kloxophpsixfour*");
			passthru("cp -rf ../../kloxophp*.tar.gz /var/cache/kloxo");
			passthru("rename ../../_kloxophpsixfour ../../kloxophpsixfour ../../_kloxophpsixfour*");
			passthru("cp -rf ../../kloxophp-version /var/cache/kloxo"); 
		}
		chdir("/usr/local/lxlabs/kloxo");
		passthru("mkdir -p /usr/local/lxlabs/kloxo/log");
	}
	else {
		chdir("/usr/local/lxlabs/kloxo");
		system("mkdir -p /usr/local/lxlabs/kloxo/log");
		@ unlink("kloxo-current.zip");
		print("Downloading latest Kloxo release\n");
		system("wget ".$downloadserver."download/kloxo/production/kloxo/kloxo-current.zip");
	}

	print("\n\nInstalling Kloxo.....\n\n");
	system("unzip -oq kloxo-current.zip", $return);

	if ($return) {
		print("Unzipping the core Failed.. Most likely it is corrupted. Report it at http://forum.lxcenter.org/\n");
		exit;
	}

	unlink("kloxo-current.zip");
	system("chown -R lxlabs:lxlabs /usr/local/lxlabs/");
	chdir("/usr/local/lxlabs/kloxo/httpdocs/");
	system("service mysqld start");

	if ($installtype !== 'slave') {
		check_default_mysql($dbroot, $dbpass);
	}
	$mypass = password_gen();

	print("Prepare defaults and configurations...\n");
	system("/usr/local/lxlabs/ext/php/php $dir_name/installall.php");
	our_file_put_contents("/etc/sysconfig/spamassassin", "SPAMDOPTIONS=\" -v -d -p 783 -u lxpopuser\"");
	print("Creating Vpopmail database...\n");
	system("sh $dir_name/vpop.sh $dbroot \"$dbpass\" lxpopuser $mypass");
	system("chmod -R 755 /var/log/httpd/");
	system("chmod -R 755 /var/log/httpd/fpcgisock >/dev/null 2>&1");
	system("mkdir -p /var/log/kloxo/");
	system("mkdir -p /var/log/news");
	system("ln -sf /var/qmail/bin/sendmail /usr/sbin/sendmail");
	system("ln -sf /var/qmail/bin/sendmail /usr/lib/sendmail");
	system("echo `hostname` > /var/qmail/control/me");
	system("service qmail restart >/dev/null 2>&1 &");
	system("service courier-imap restart >/dev/null 2>&1 &");

/*
	// make install failed
	
	$dbfile="/home/kloxo/httpd/webmail/horde/scripts/sql/create.mysql.sql";
	if(file_exists($dbfile)) {
		if($dbpass == "") {
			system("mysql -u $dbroot  <$dbfile");
		} else {
			system("mysql -u $dbroot -p$dbpass <$dbfile");
		}
	}
*/
	system("mkdir -p /home/kloxo/httpd");
	chdir("/home/kloxo/httpd");
	@ unlink("skeleton-disable.zip");
	system("chown -R lxlabs:lxlabs /home/kloxo/httpd");
	system("/etc/init.d/kloxo restart >/dev/null 2>&1 &");
	chdir("/usr/local/lxlabs/kloxo/httpdocs/");
	system("/usr/local/lxlabs/ext/php/php /usr/local/lxlabs/kloxo/bin/install/create.php --install-type=$installtype --db-rootuser=$dbroot --db-rootpassword=$dbpass");
	system("/usr/local/lxlabs/ext/php/php /usr/local/lxlabs/kloxo/bin/misc/secure-webmail-mysql.phps");
	system("/bin/rm /usr/local/lxlabs/kloxo/bin/misc/secure-webmail-mysql.phps");
	system("/script/centos5-postpostupgrade");
	if ($installappinst) {
		system("/script/installapp-update"); // First run (gets installappdata)
		system("/script/installapp-update"); // Second run (gets applications)
	}

	// --- remove all temporal flags because the end of install
	system("rm -rf /var/cache/kloxo/*-version");
	system("rm -rf /var/cache/kloxo/kloxo-install-*.flg");

	//--- for prevent mysql socket problem (especially on 64bit system)
	if (!file_exists("/var/lib/mysql/mysql.sock")) {
		system("/etc/init.d/mysqld stop");
		system("mksock /var/lib/mysql/mysql.sock");	
		system("/etc/init.d/mysqld start");
	}

	//--- running before finish for all setting running well
//	passthru("sh /script/cleanup");
	
	print("\nCongratulations. Kloxo has been installed succesfully on your server as $installtype\n\n");
	if ($installtype === 'master') {
		print("You can connect to the server at:\n");
		print("    https://<ip-address>:7777 - secure ssl connection, or\n");
		print("    http://<ip-address>:7778 - normal one.\n\n");
		print("The login and password are 'admin' 'admin'. After Logging in, you will have to\n");
		print("change your password to something more secure\n\n");
		print("We hope you will find managing your hosting with Kloxo\n");
		print("refreshingly pleasurable, and also we wish you all the success\n");
		print("on your hosting venture\n\n");
		print("Thanks for choosing Kloxo to manage your hosting, and allowing us to be of\n");
		print("service\n");
	} else {
		print("You should open the port 7779 on this server, since this is used for\n");
		print("the communication between master and slave\n\n");
		print("To access this slave, to go admin->servers->add server,\n");
		print("give the ip/machine name of this server. The password is 'admin'.\n\n");
		print("The slave will appear in the list of slaves, and you can access it\n");
		print("just like you access localhost\n\n");
	}
	print("\n");
	print("---------------------------------------------\n");
	print("* Note: Better reboot after Kloxo install\n\n");
}

lxins_main();
