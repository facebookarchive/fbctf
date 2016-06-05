# FBCTF [![Build Status](https://travis-ci.org/facebook/fbctf.svg?branch=master)](https://travis-ci.org/facebook/fbctf)

## What is FBCTF?

The Facebook CTF is a platform to host Jeopardy and “King of the Hill” style Capture the Flag competitions.

<div align="center"><img src="screencapture.gif" /></div>

## How do I use FBCTF?

* Organize a competition. This can be with as few as two participants, all the way up to several hundred. The participants can be physically present, active online, or a combination of the two.
* Follow setup instructions below to spin up platform infrastructure.
* Enter challenges into admin page
* Have participants register as teams
    * If running a closed competition:
        * In the admin page, generate and export tokens to be shared with approved teams, then point participants towards the registration page
    * If running an open competition:
        * Point participants towards the registration page
* Enjoy!

# Installation

The Facebook CTF platform can be provisioned in development or production environments. Note that the *only* supported system is 64 bit Ubuntu 14.04. Ubuntu 16.04 is not supported at this time, nor is 32 bit. We will accept PRs to support other platforms, but we will not officially support those platforms if there any issues.

### Development

While it is possible to do development on a physical Ubuntu machine (and possibly other Linux distros as well), we highly recommend doing all development on a Vagrant VM. First, install [VirtualBox](https://www.virtualbox.org/wiki/Downloads) and [Vagrant](https://www.vagrantup.com/downloads.html). Then run:

```bash
git clone https://github.com/facebook/fbctf
cd fbctf
vagrant up
```

This will create a local virtual machine with Ubuntu 14.04 using Vagrant and VirtualBox as the provider. The provisioning script will install all necessary software to the platform locally, using self-signed certificates. The platform will be available on [https://10.10.10.5](https://10.10.10.5) by default. You can find any error logs in `/var/log/hhvm/error.log`. If you would like to change this IP address, you can find the configuration for it in the `Vagrantfile`.

Once the VM has started, go to the URL/IP of the server (10.10.10.5 in the default case). Click the "Login" link at the top right, enter the 'admin' as the team name and 'password' as the password (without quotes). You will be redirected to the administration page. At the bottom of the navigation bar on the left, there will be a link to go to the gameboard.

If you are using a non-English locale on the host system, you will run into problems during the installation. The easiest solution is to run vagrant with a default English locale:

```bash
LC_ALL=en_US.UTF-8 LANG=en_US.UTF-8 vagrant up
```

Note that if you don't want to use the Vagrant VM (not recommended), you can provision in dev mode manually. To do so, run the following commands:

```
sudo apt-get install git
git clone https://github.com/facebook/fbctf
cd fbctf
./extra/provision.sh -m dev -s $PWD
```

### Production

The target system needs to be 64 bit Ubuntu 14.04. Run the following commands:

```bash
sudo apt-get install git
git clone https://github.com/facebook/fbctf
cd fbctf
./extra/provision.sh -m prod -s $PWD
```

*Note*: Because this is a production environment, the password will be randomly generated when the provision script finishes. This ensures that you can't forget to change the default password after provisioning. Make sure to watch the very end of the provision script, as the password will be printed out. It will not be stored elsewhere, so either keep track of it or change it. In order to change the password, run the following command:

```
set_password new_password ctf ctf fbctf $PWD
```

This will set the password to 'new_password', assuming the database user/password is ctf/ctf and the database name is fbctf (these are the defaults).

By default, the provision script will place the code in the `/var/www/fbctf` directory, install all dependencies, and start the server. In order to run in production mode, we require that you use SSL. You can choose between generating new self-signed, using your own or generate valid SSL certificates using [Let's Encrypt](https://letsencrypt.org/). The provision script uses [certbot](https://certbot.eff.org/) to assist with the generation of valid SSL certificates. Use the help to see all the available options with the command:

```
./extra/provision -h
```

Once you've provisioned the VM, go to the URL/IP of the server. Click the "Login" link at the top right, enter the admin credentials, and you'll be redirected to the admin page. Enter the credentials you received at the end of the provision script to log in.

#### Optional installation

If you are going to be modifying files outside of the Vagrant VM, you will need to synchronize the files using [Unison](https://www.cis.upenn.edu/~bcpierce/unison/download.html) (bi-directional file sync over SSH). Once Unison is installed, you can sync your local repo with the VM with the following command:

`./extra/unison.sh PATH_TO_CTF_FOLDER`

Note that the unison script will not sync NPM dependencies, so if you ever need to run `npm install`, you should always run it on the VM itself.

This step is not necessary if all development is done on the VM.

## Reporting an Issue

First, ensure the issue was not already reported by doing a search. If you cannot find an existing issue, create a new issue. Make the title and description as clear as possible, and include a test case or screenshot to reproduce or illustrate the problem if possible.

If you have issues installing the platform, please provide the entire output of the provision script in your issue. Also include any error messages you find in `/var/log/hhvm/error.log`.

## Contribute

You’ve used it, now you want to make it better? Awesome! Pull requests are welcome! Click [here] (https://github.com/facebook/fbctf/blob/master/CONTRIBUTING.md) to find out how to contribute.

Facebook also has [bug bounty program] (https://www.facebook.com/whitehat/) that includes FBCTF. If you find a security vulnerability in the platform, please submit it via the process outlined on that page and do not file a public issue.

## Have more questions?

Check out the [wiki pages] (https://github.com/facebook/fbctf/wiki) attached to this repo.

## License

This source code is licensed under the Creative Commons Attribution-NonCommercial 4.0 International license found in the LICENSE file in the root directory of this source tree.
