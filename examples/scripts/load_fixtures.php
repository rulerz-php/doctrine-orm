#!/usr/bin/env php
<?php

/** @var \Doctrine\ORM\EntityManager $em */
list($em, $rulerz) = require_once __DIR__.'/../bootstrap.php';

$fixtures = json_decode(file_get_contents(__DIR__.'/../../vendor/kphoen/rulerz/examples/fixtures.json'), true);

$groups = [];

echo sprintf("\e[32mLoading fixtures for %d groups\e[0m".PHP_EOL, count($fixtures['groups']));

foreach ($fixtures['groups'] as $slug => $group) {
    $groups[$slug] = new \Entity\Group($group['name']);

    $em->persist($groups[$slug]);
}
$em->flush();

echo sprintf("\e[32mLoading fixtures for %d players\e[0m".PHP_EOL, count($fixtures['players']));

foreach ($fixtures['players'] as $player) {
    $em->persist(new \Entity\Player(
        $player['pseudo'],
        $player['fullname'],
        $player['gender'],
        $player['points'],
        $groups[$player['group']],
        new \DateTime($player['birthday']),
        new \Entity\Address(
            $player['address']['street'],
            $player['address']['postalCode'],
            $player['address']['city'],
            $player['address']['country']
        )
    ));
}

$em->flush();
