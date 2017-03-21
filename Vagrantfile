# -*- mode: ruby -*-
# vi: set ft=ruby :

VAGRANTFILE_API_VERSION = "2"

Vagrant.configure(VAGRANTFILE_API_VERSION) do |config|
  config.vm.box = "ubuntu/trusty64"
  config.vm.network "private_network", ip: "10.10.10.5"
  config.vm.hostname = "facebookCTF-Dev"
  config.ssh.shell = "bash -c 'BASH_ENV=/etc/profile exec bash'"
  config.vm.provision "shell", path: "extra/provision.sh", args: ENV['FBCTF_PROVISION_ARGS'], privileged: false
  config.vm.provider "virtualbox" do |v|
    v.memory = [ENV['FBCTF_VM_MEMORY'].to_i, 4096].max
    v.cpus = [ENV['FBCTF_VM_CPUS'].to_i, 4].max
  end
end
