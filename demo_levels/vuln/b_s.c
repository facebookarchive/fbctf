#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <stdbool.h>
#define NUMHANDLERS 6

typedef struct input_handler {
    char cmd[32];
    void (*handler)(char *);
} input_handler;

bool admin = false;
char admin_password[64];
input_handler handlers[NUMHANDLERS];

input_handler *find_handler(char *cmd){
    int i;
    for(i = 0; i < NUMHANDLERS; i++){
        if (!strcmp(handlers[i].cmd, cmd)){
            return &handlers[i];
        }
    }

    return NULL;
}

void lol_handler(char *arg){
    if (arg == NULL){
        printf("usage: lol [string]\n");
        return;
    }

    printf("lol %s\n", arg);
}

void add_handler(char *arg){
    int arg1;
    int arg2;

    if (arg == NULL){
        printf("usage: add [num1] [num2]\n");
        return;
    }

    sscanf(arg, "%d %d", &arg1, &arg2);

    printf("= %d\n", arg1 + arg2);
}

void mult_handler(char * arg){
    int arg1;
    int arg2;

    if (arg == NULL){
        printf("usage: mult [num1] [num2]\n");
        return;
    }

    sscanf(arg, "%d %d", &arg1, &arg2);

    printf("= %d\n", arg1 * arg2);
}

void rename_handler(char *arg){
    char *existing;
    char *new;

    if (arg == NULL){
        printf("usage: rename [cmd_name] [new_name]\n");
        return;
    }

    existing = strtok(arg, " ");
    new = strtok(NULL, "");

    if (new == NULL){
        printf("usage: rename [cmd_name] [new_name]\n");
        return;
    }

    input_handler *found = find_handler(existing);

    if (found != NULL){
        strcpy(found->cmd, new);
    }else{
        printf("No command found.\n");
    }
}

void auth_admin_handler(char *arg){
    if (arg == NULL){
        printf("usage: auth [password]\n");
        return;
    }

    if (!strcmp(arg, admin_password)){
        admin = true;
        printf("You are now admin!\n");
    }else{
        printf("Incorrect password!\n");
    }
}

void shell_handler(char *arg){
    if (admin){
        gid_t gid = getegid();
        setresgid(gid, gid, gid);
        system("/bin/sh");
    }else{
        printf("You must be admin!\n");
    }
}

void setup_handlers(){
    handlers[0] = (input_handler){"shell", shell_handler};
    handlers[1] = (input_handler){"auth", auth_admin_handler};
    handlers[2] = (input_handler){"rename", rename_handler};
    handlers[3] = (input_handler){"add", add_handler};
    handlers[4] = (input_handler){"mult", mult_handler};
    handlers[5] = (input_handler){"lol", lol_handler};
}

void input_loop(){
    char input_buf[128];
    char *cmd;
    char *arg;
    input_handler *handler;

    printf(">> ");
    fflush(stdout);
    while(fgets(input_buf, 128, stdin)){
        cmd = strtok(input_buf, " \n");
        arg  = strtok(NULL, "\n");

         handler = find_handler(cmd);

         if (handler != NULL){
             handler->handler(arg);
         }else{
             printf("Command \"%s\" not found!\n", cmd);
         }

        printf(">> ");
        fflush(stdout);
    }
}

int main(int argc, char **argv){
    FILE *f = fopen("/home/best_shell/password.txt","r");
    if (f == NULL){
        printf("Cannot open password file");
        exit(1);
    }

    fgets(admin_password, 64, f);
    admin_password[strcspn(admin_password, "\n")] = '\0';
    fclose(f);

    setup_handlers();
    input_loop();

    return 0;
}
