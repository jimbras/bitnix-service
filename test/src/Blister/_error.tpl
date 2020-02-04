<?php

function __foo() {
    echo $a;
}

function __bar() {
    echo $a;
}

@__foo();
__bar();
