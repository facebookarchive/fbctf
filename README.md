# FBCTF [![Build Status](https://travis-ci.com/facebook/fbctf.svg?token=iA22NGxXs7orkhB2bopa&branch=master)](https://travis-ci.com/facebook/fbctf)

~AWESOME!!!!

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

The Facebook CTF platform can be provisioned in development or production environments.

### Production

The target system needs to be Ubuntu 14.04. Run the following commands:

```bash
sudo apt-get install git
git clone https://github.com/facebook/fbctf
cd fbctf
./extra/provision.sh prod `pwd`
```

This will place the code in the `/var/www/fbctf` directory, install all dependencies, and start the server. Be ready to provide the path for your SSL certificate's CSR and key files. More information on setting up SSL is specific in the next session, but note that if you are just testing out the platform and not running it production, you want to use the instructions listed in the Development section below, as this takes care generating certificates for you. We will provide more info on generating your own certificates for production in the future.

The password for the user `admin` will be printed in the console at the end of provisioning, as it is randomly generated everytime the CTF platform is provisioned. We will add a way to change this password from the command line in the near future (in the meantime, you can figure out how to do it manually by looking at the `import_empty_db` function in `./extra/lib.sh`.

Once you've provisioned the VM, go to the URL/IP of the server. Click the "Login" link at the top right, enter the admin credentials, and you'll be redirected to the admin page.

### Development

While it is possible to do development on a physical Ubuntu machine (and possibly other Linux distros as well), we highly recommend doing all development on a Vagrant VM. First, install [VirtualBox](https://www.virtualbox.org/wiki/Downloads) and [Vagrant](https://www.vagrantup.com/downloads.html). Then run:

```bash
git clone https://github.com/facebook/fbctf
cd fbctf
vagrant up
```

This will create a local virtual machine with Ubuntu 14.04 using Vagrant and VirtualBox as the provider. The provisioning script will install all necessary software to the platform locally, using self-signed certificates. The credentials will be admin/password and the machine will be available on [https://10.10.10.5](https://10.10.10.5) by default. You can find any error logs in `/var/log/hhvm/error.log`.


Note that if you don't want to use the Vagrant VM (not recommended), you can provision in dev mode manually. To do so, run the following commands:

```
sudo apt-get install git
git clone https://github.com/facebook/fbctf
cd fbctf
./extra/provision.sh dev `pwd`
```

Once you've provisioned the VM, go to the URL/IP of the server. Click the "Login" link at the top right, enter the admin credentials, and you'll be redirected to the admin page.

#### Optional installation

If you are going to be modifying files outside of the Vagrant VM, you will need to synchronize the files using [Unison](https://www.cis.upenn.edu/~bcpierce/unison/download.html) (bi-directional file sync over SSH). Once Unison is installed, you can sync your local repo with the VM with the following command:

`./extra/unison.sh PATH_TO_CTF_FOLDER`

Note that the unison script will not sync NPM dependencies, so if you ever need to run `npm install`, you should always run it on the VM itself.

This step is not necessary if all development is done on the VM.

## Contribute

You’ve used it, now you want to make it better? Awesome! Pull requests are welcome! Click [here] (https://github.com/facebook/fbctf/blob/master/CONTRIBUTING.md) to find out how to contribute.

Facebook also has [bug bounty program] (https://www.facebook.com/whitehat/) that includes FBCTF. If you find a security vulnerability in the platform, please submit it via the process outlined on that page and do not file a public issue.

## Have more questions?

Check out the [wiki pages] (https://github.com/facebook/fbctf/wiki) attached to this repo.

## License

This source code is licensed under the Creative Commons Attribution-NonCommercial 4.0 International license found in the LICENSE file in the root directory of this source tree.
