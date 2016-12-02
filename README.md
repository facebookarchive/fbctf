# FBCTF [![Build Status](https://travis-ci.org/facebook/fbctf.svg)](https://travis-ci.org/facebook/fbctf)

## What is FBCTF?

The Facebook CTF is a platform to host Jeopardy and “King of the Hill” style Capture the Flag competitions.

<div align="center"><img src="screencapture.gif" /></div>

## How do I use FBCTF?

* Organize a competition. This can be done with as few as two participants, all the way up to several hundred. The participants can be physically present, active online, or a combination of the two.
* Follow setup instructions below to spin up platform infrastructure.
* Enter challenges into admin page
* Have participants register as teams
    * If running a closed competition:
        * In the admin page, generate and export tokens to be shared with approved teams, then point participants towards the registration page
    * If running an open competition:
        * Point participants towards the registration page
* Enjoy!
* Note: We're hiring! If you're interested in the remote eng lead position, apply [here](http://pro.applytojob.com/apply/Qe1YmW/CTF-Engineering-Lead)!

# Installation

The Facebook CTF platform can be provisioned in development or production environments. Note that the *only* supported system is 64 bit Ubuntu 14.04. Ubuntu 16.04 is not supported at this time, nor is 32 bit. We will accept PRs to support other platforms, but we will not officially support those platforms if there are any issues.

### Development

While it is possible to do development on a physical Ubuntu machine (and possibly other Linux distros as well), we highly recommend doing all development on a Vagrant VM. First, install [VirtualBox](https://www.virtualbox.org/wiki/Downloads) and [Vagrant](https://www.vagrantup.com/downloads.html). Then run:

```bash
git clone https://github.com/facebook/fbctf
cd fbctf
vagrant up
```

This will create a local virtual machine with Ubuntu 14.04 using Vagrant and VirtualBox as the provider. The provisioning script will install all necessary software to the platform locally, using self-signed certificates. The platform will be available on [https://10.10.10.5](https://10.10.10.5) by default. You can find any error logs in `/var/log/hhvm/error.log`. If you would like to change this IP address, you can find the configuration for it in the `Vagrantfile`.

Once the VM has started, go to the URL/IP of the server (10.10.10.5 in the default case). Click the "Login" link at the top right, enter the 'admin' as the team name and 'password' as the password (without quotes). You will be redirected to the administration page. At the bottom of the navigation bar on the left, there will be a link to go to the gameboard.

To login to the Vagrant VM, navigate to the fbctf folder and run:

```
vagrant ssh
```

If you are using a non-English locale on the host system, you will run into problems during the installation. The easiest solution is to run vagrant with a default English locale:

```bash
LC_ALL=en_US.UTF-8 LANG=en_US.UTF-8 vagrant up
```

Note that if you don't want to use the Vagrant VM (not recommended), you can provision in dev mode manually. To do so, run the following commands:

```
sudo apt-get update
sudo apt-get install git
git clone https://github.com/facebook/fbctf
cd fbctf
./extra/provision.sh -m dev -s $PWD
```

### Production
Please note that your machine needs to have at least 2GB of RAM, otherwise the composer part of the installation will fail.
The target system needs to be 64 bit Ubuntu 14.04. Run the following commands:

```bash
sudo apt-get update
sudo apt-get install git
git clone https://github.com/facebook/fbctf
cd fbctf
./extra/provision.sh -m prod -s $PWD
```

*Note*: Because this is a production environment, the password will be randomly generated when the provision script finishes. This ensures that you can't forget to change the default password after provisioning. Make sure to watch the very end of the provision script, as the password will be printed out. It will not be stored elsewhere, so either keep track of it or change it. In order to change the password, run the following command:

```
source ./extra/lib.sh
set_password new_password ctf ctf fbctf $PWD
```

This will set the password to 'new_password', assuming the database user/password is ctf/ctf and the database name is fbctf (these are the defaults).

By default, the provision script will place the code in the `/var/www/fbctf` directory, install all dependencies, and start the server. In order to run in production mode, we require that you use SSL. You can choose between generating new self-signed, using your own or generate valid SSL certificates using [Let's Encrypt](https://letsencrypt.org/). The provisioning script uses [certbot](https://certbot.eff.org/) to assist with the generation of valid SSL certificates.

#### Production Certificates

As mentioned above, there are three different type of certificates that the provision script will use:

1. 	Self-signed certificate (```-c self```):
	It is the same type of certificate that the development mode provisioning. Both the CRT and the key files will be generated and the command could be:

	```
	./extra/provision.sh -m prod -c self -s $PWD
	``` 

2. 	Use of own certificate (```-c own```):
	If we already have a valid SSL certificate for our domain and want to use it. If the path for both CRT and key files is not provided, it will be prompted. Example command:

	```
	./extra/provision.sh -m prod -c own -k /path/to/my.key -C /path/to/cert.crt -s $PWD
	```	

3. 	Generate new certificate using Let's Encrypt (```-c certbot```):
	A new valid SSL certificate will be generated using [certbot](https://certbot.eff.org/). There are few needed parameters that if not provided, will be prompted:

	```
	./extra/provision.sh -m prod -c certbot -D test.mydomain.com -e myemail@mydomain.com -s $PWD
	```
	Note that certbot will run a challenge-response to validate the server so it will need Internet access.

Once you've provisioned the VM, go to the URL/IP of the server. Click the "Login" link at the top right, enter the admin credentials, and you'll be redirected to the admin page. Enter the credentials you received at the end of the provision script to log in.


#### Optional installation

If you are going to be modifying files outside of the Vagrant VM, you will need to synchronize the files using [Unison](https://www.cis.upenn.edu/~bcpierce/unison/download.html) (bi-directional file sync over SSH). Once Unison is installed, you can sync your local repo with the VM with the following command from the root of the repository (outside the VM):

`./extra/unison.sh $PWD`

Note that the unison script will not sync NPM dependencies, so if you ever need to run `npm install`, you should always run it on the VM itself.

This step is not necessary if all development is done on the VM.


#### Keep code updated

If you are already running the fbctf platform and want to keep the code updated, there is an easy way to do that with the provision script.
For example, the following command will run in a production environment and it will pull master from Github and get it ready to run, from the folder ```/var/www/fbctf```:

```
./extra/provision.sh -m prod -U -s $PWD -d /var/www/fbctf
```


# Using Docker

[Dockerfile](Dockerfile) is provided, you can use docker to deploy fbctf to both development and production.

### Development

This build command will provision fbctf in dev mode in docker:

`docker build --build-arg MODE=dev -t="fbctf_in_dev" .` (don't forget the dot at the end)


Run command:

`docker run -p 80:80 -p 443:443 fbctf_in_dev`

Once you've started the container, go to the URL/IP of the server. Click the "Login" link at the top right, enter the default admin credentials, and you'll be redirected to the admin page.

### Production
Please note that your machine needs to have at least 2GB of RAM, otherwise the composer part of the installation will fail.
This build command will provision fbctf in prod mode in docker. Once you've started the container, Let's Encrypt will take YOUR_DOMAIN to obtain a trusted certificate at zero cost:

`docker build --build-arg MODE=prod --build-arg DOMAIN=test.mydomain.com --build-arg EMAIL=myemail@mydomain.com --build-arg TYPE=certbot -t="fbctf_in_prod" .` (don't forget the dot at the end)

Run command:

`docker run -p 80:80 -p 443:443 -v /etc/letsencrypt:/etc/letsencrypt fbctf_in_prod`

*Note 1: Because this is a production environment, the password will be randomly generated when the provision script finishes. Make sure to watch the very end of the provision script while the image is building, as the password will be printed out. It will not be stored elsewhere, so either keep track of it or change it. In order to change the password, run the following command in container:*

```
set_password new_password ctf ctf fbctf /root
```

*This will set the password to 'new_password', assuming the database user/password is ctf/ctf and the database name is fbctf (these are the defaults).*

*Note 2: You'll have to mount /etc/letsencrypt as a volume to persist the certs. ex: `docker run -v /etc/letsencrypt:/etc/letsencrypt ...`*

You can go to the URL/IP of the server, click the "Login" link at the top right, enter the admin credentials, and you'll be redirected to the admin page.

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
