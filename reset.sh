#!/bin/sh
php artisan migrate:refresh --seed
php artisan exercise:import