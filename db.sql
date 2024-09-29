/*Create ntask basic database with this script.*/
drop database if exists ntask;

create database ntask;

use ntask;

create table user (
    id int primary key not null auto_increment,
    username varchar(255) not null,
    email varchar(255) not null,
    password varchar(255) not null,
    admin tinyint not null default 0
);

create table task (
    id int primary key not null auto_increment,
    name varchar(255) not null,
    description text,
    completed boolean not null default false,
    image mediumblob,
    due_date timestamp
);