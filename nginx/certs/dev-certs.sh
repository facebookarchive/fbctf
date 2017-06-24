sudo openssl req -nodes -newkey rsa:2048 -keyout "dev.key" -out "dev.csr" -subj "/O=Facebook CTF"
sudo openssl x509 -req -days 365 -in "dev.csr" -signkey "dev.key" -out "dev.cert"
sudo openssl dhparam -out "dhparam.pem" 2048
