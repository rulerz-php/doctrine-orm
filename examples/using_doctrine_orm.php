<?php

declare(strict_types=1);

/** @var \Doctrine\ORM\EntityManager $em */
/* @var \Doctrine\DBAL\Connection $connection */
list($em, $rulerz) = require_once __DIR__.'/bootstrap.php';

$queryBuilder = $entityManager
    ->createQueryBuilder()
    ->select('p')
    ->from(Entity\Player::class, 'p')
    ->leftJoin('p.group', 'gr');

// 1. Write a rule.
$rule = 'gender = :gender';

// 2. Define the parameters.
$parameters = [
    'gender' => 'F',
];

// 3. Enjoy!
$players = $rulerz->filter($queryBuilder, $rule, $parameters);

var_dump(iterator_to_array($players));
