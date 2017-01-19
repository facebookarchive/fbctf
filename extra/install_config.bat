@echo off
SETLOCAL ENABLEDELAYEDEXPANSION
set /p numberOfServers=How many extra databases do you need?
echo # -*- mode: ruby -*- > ../Vagrantfile
echo #vi: set ft=ruby : >> ../Vagrantfile
echo. >> ../Vagrantfile
echo VAGRANTFILE_API_VERSION = "2" >> ../Vagrantfile
echo. >> ../Vagrantfile
echo Vagrant.configure(VAGRANTFILE_API_VERSION) do ^|config^| >> ../Vagrantfile
echo   config.vm.box = "ubuntu/trusty64" >> ../Vagrantfile
echo   config.ssh.shell = "bash -c 'BASH_ENV=/etc/profile exec bash'" >> ../Vagrantfile
echo   #This runs the provision script on both servers >> ../Vagrantfile
echo. >> ../Vagrantfile
echo   #Main server that runs the FBCTF >> ../Vagrantfile
echo   config.vm.define "main" do ^|main^| >> ../Vagrantfile
echo     main.vm.network "private_network", ip: "10.10.10.5" >> ../Vagrantfile
echo     main.vm.hostname = "facebookCTF-Dev" >> ../Vagrantfile
echo     main.vm.provision "shell", path: "extra/provision.sh", args: "'-r %numberOfServers%' '-N 1' ENV[\'FBCTF_PROVISION_ARGS\']", privileged: true >> ../Vagrantfile
echo     config.vm.provider "virtualbox" do ^|v^| >> ../Vagrantfile
echo       v.memory = 4096 >> ../Vagrantfile
echo       v.cpus = 4 >> ../Vagrantfile
echo     end >> ../Vagrantfile
echo   end >> ../Vagrantfile
FOR /L %%i in (1,1,%numberOfServers%) DO (
SET /A addr=%%i+5
SET /A serverNumber=%%i+1
echo. >> ../Vagrantfile
echo   #Replication server >> ../Vagrantfile
echo   config.vm.define "replication%%i" do ^|replication%%i^| >> ../Vagrantfile
echo     replication%%i.vm.network "private_network", ip: "10.10.10.!addr!" >> ../Vagrantfile
echo     replication%%i.vm.hostname = "fbctf-dbreplication" >> ../Vagrantfile
echo     replication%%i.vm.provision "shell", path: "extra/replication.sh", args: "'-r %numberOfServers%' '-N !serverNumber!' ENV[\'FBCTF_PROVISION_ARGS\']", privileged: true >> ../Vagrantfile
echo     config.vm.provider "virtualbox" do ^|v^| >> ../Vagrantfile
echo       v.memory = 2048 >> ../Vagrantfile
echo       v.cpus = 2 >> ../Vagrantfile
echo     end >> ../Vagrantfile
echo   end >> ../Vagrantfile
)
echo end >> ../Vagrantfile