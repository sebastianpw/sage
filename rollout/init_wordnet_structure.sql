-- Wordnet Schema: Converted to InnoDB and Optimized for Performance
--
-- Key Changes:
-- 1. ENGINE changed from MyISAM to InnoDB for all tables.
-- 2. Character set upgraded from utf8mb3 to utf8mb4 for full Unicode support.
-- 3. All FOREIGN KEY constraints have been REMOVED as requested.
-- 4. Secondary indexes (KEY) are kept to ensure fast query performance on joins.
--

-- ========================================================
-- TABLES
-- ========================================================

DROP TABLE IF EXISTS `adjpositions`;
CREATE TABLE `adjpositions` (
  `synsetid` int(10) unsigned NOT NULL DEFAULT 0,
  `wordid` int(10) unsigned NOT NULL DEFAULT 0,
  `position` enum('a','p','ip') NOT NULL,
  PRIMARY KEY (`synsetid`,`wordid`),
  KEY `idx_wordid` (`wordid`),
  KEY `idx_position` (`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `adjpositiontypes`;
CREATE TABLE `adjpositiontypes` (
  `position` enum('a','p','ip') NOT NULL,
  `positionname` varchar(24) NOT NULL,
  PRIMARY KEY (`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `casedwords`;
CREATE TABLE `casedwords` (
  `casedwordid` int(10) unsigned NOT NULL DEFAULT 0,
  `wordid` int(10) unsigned NOT NULL DEFAULT 0,
  `cased` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`casedwordid`),
  KEY `idx_wordid` (`wordid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `lexdomains`;
CREATE TABLE `lexdomains` (
  `lexdomainid` smallint(5) unsigned NOT NULL DEFAULT 0,
  `lexdomainname` varchar(32) DEFAULT NULL,
  `lexdomain` varchar(32) DEFAULT NULL,
  `pos` enum('n','v','a','r','s') DEFAULT NULL,
  PRIMARY KEY (`lexdomainid`),
  KEY `idx_pos` (`pos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `lexlinks`;
CREATE TABLE `lexlinks` (
  `synset1id` int(10) unsigned NOT NULL DEFAULT 0,
  `word1id` int(10) unsigned NOT NULL DEFAULT 0,
  `synset2id` int(10) unsigned NOT NULL DEFAULT 0,
  `word2id` int(10) unsigned NOT NULL DEFAULT 0,
  `linkid` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`word1id`,`synset1id`,`word2id`,`synset2id`,`linkid`),
  KEY `idx_synset1id` (`synset1id`),
  KEY `idx_synset2id` (`synset2id`),
  KEY `idx_word2id` (`word2id`),
  KEY `idx_linkid` (`linkid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `linktypes`;
CREATE TABLE `linktypes` (
  `linkid` smallint(5) unsigned NOT NULL DEFAULT 0,
  `link` varchar(50) DEFAULT NULL,
  `recurses` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`linkid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `morphmaps`;
CREATE TABLE `morphmaps` (
  `wordid` int(10) unsigned NOT NULL DEFAULT 0,
  `pos` enum('n','v','a','r','s') NOT NULL DEFAULT 'n',
  `morphid` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`morphid`,`pos`,`wordid`),
  KEY `idx_wordid_pos` (`wordid`, `pos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `morphs`;
CREATE TABLE `morphs` (
  `morphid` int(10) unsigned NOT NULL DEFAULT 0,
  `morph` varchar(70) NOT NULL,
  PRIMARY KEY (`morphid`),
  KEY `idx_morph` (`morph`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `postypes`;
CREATE TABLE `postypes` (
  `pos` enum('n','v','a','r','s') NOT NULL,
  `posname` varchar(20) NOT NULL,
  PRIMARY KEY (`pos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `samples`;
CREATE TABLE `samples` (
  `synsetid` int(10) unsigned NOT NULL DEFAULT 0,
  `sampleid` smallint(5) unsigned NOT NULL DEFAULT 0,
  `sample` mediumtext NOT NULL,
  PRIMARY KEY (`synsetid`,`sampleid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `semlinks`;
CREATE TABLE `semlinks` (
  `synset1id` int(10) unsigned NOT NULL DEFAULT 0,
  `synset2id` int(10) unsigned NOT NULL DEFAULT 0,
  `linkid` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`synset1id`,`synset2id`,`linkid`),
  KEY `idx_synset2id` (`synset2id`),
  KEY `idx_linkid` (`linkid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `senses`;
CREATE TABLE `senses` (
  `wordid` int(10) unsigned NOT NULL DEFAULT 0,
  `casedwordid` int(10) unsigned DEFAULT NULL,
  `synsetid` int(10) unsigned NOT NULL DEFAULT 0,
  `senseid` int(10) unsigned DEFAULT NULL,
  `sensenum` smallint(5) unsigned NOT NULL DEFAULT 0,
  `lexid` smallint(5) unsigned NOT NULL DEFAULT 0,
  `tagcount` int(10) unsigned DEFAULT NULL,
  `sensekey` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`wordid`,`synsetid`),
  KEY `idx_synsetid` (`synsetid`),
  KEY `idx_casedwordid` (`casedwordid`),
  KEY `idx_sensekey` (`sensekey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `synsets`;
CREATE TABLE `synsets` (
  `synsetid` int(10) unsigned NOT NULL DEFAULT 0,
  `pos` enum('n','v','a','r','s') NOT NULL,
  `lexdomainid` smallint(5) unsigned NOT NULL DEFAULT 0,
  `definition` mediumtext DEFAULT NULL,
  PRIMARY KEY (`synsetid`),
  KEY `idx_pos` (`pos`),
  KEY `idx_lexdomainid` (`lexdomainid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `vframemaps`;
CREATE TABLE `vframemaps` (
  `synsetid` int(10) unsigned NOT NULL DEFAULT 0,
  `wordid` int(10) unsigned NOT NULL DEFAULT 0,
  `frameid` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`synsetid`,`wordid`,`frameid`),
  KEY `idx_wordid` (`wordid`),
  KEY `idx_frameid` (`frameid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `vframes`;
CREATE TABLE `vframes` (
  `frameid` smallint(5) unsigned NOT NULL DEFAULT 0,
  `frame` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`frameid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `vframesentencemaps`;
CREATE TABLE `vframesentencemaps` (
  `synsetid` int(10) unsigned NOT NULL DEFAULT 0,
  `wordid` int(10) unsigned NOT NULL DEFAULT 0,
  `sentenceid` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`synsetid`,`wordid`,`sentenceid`),
  KEY `idx_wordid` (`wordid`),
  KEY `idx_sentenceid` (`sentenceid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `vframesentences`;
CREATE TABLE `vframesentences` (
  `sentenceid` smallint(5) unsigned NOT NULL DEFAULT 0,
  `sentence` mediumtext DEFAULT NULL,
  PRIMARY KEY (`sentenceid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `words`;
CREATE TABLE `words` (
  `wordid` int(10) unsigned NOT NULL DEFAULT 0,
  `lemma` varchar(80) NOT NULL,
  PRIMARY KEY (`wordid`),
  KEY `idx_lemma` (`lemma`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ========================================================
-- VIEWS (created after tables)
-- ========================================================

DROP VIEW IF EXISTS `adjectiveswithpositions`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `adjectiveswithpositions` AS select `senses`.`synsetid` AS `synsetid`,`senses`.`wordid` AS `wordid`,`senses`.`casedwordid` AS `casedwordid`,`senses`.`senseid` AS `senseid`,`senses`.`sensenum` AS `sensenum`,`senses`.`lexid` AS `lexid`,`senses`.`tagcount` AS `tagcount`,`senses`.`sensekey` AS `sensekey`,`adjpositions`.`position` AS `position`,`words`.`lemma` AS `lemma`,`synsets`.`pos` AS `pos`,`synsets`.`lexdomainid` AS `lexdomainid`,`synsets`.`definition` AS `definition` from (((`senses` join `adjpositions` on(`senses`.`wordid` = `adjpositions`.`wordid` and `senses`.`synsetid` = `adjpositions`.`synsetid`)) left join `words` on(`senses`.`wordid` = `words`.`wordid`)) left join `synsets` on(`senses`.`synsetid` = `synsets`.`synsetid`));

DROP VIEW IF EXISTS `samplesets`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `samplesets` AS select `samples`.`synsetid` AS `synsetid`,group_concat(distinct `samples`.`sample` order by `samples`.`sampleid` ASC separator '|') AS `sampleset` from `samples` group by `samples`.`synsetid`;

DROP VIEW IF EXISTS `dict`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `dict` AS select `s`.`synsetid` AS `synsetid`,`words`.`wordid` AS `wordid`,`s`.`casedwordid` AS `casedwordid`,`words`.`lemma` AS `lemma`,`s`.`senseid` AS `senseid`,`s`.`sensenum` AS `sensenum`,`s`.`lexid` AS `lexid`,`s`.`tagcount` AS `tagcount`,`s`.`sensekey` AS `sensekey`,`casedwords`.`cased` AS `cased`,`synsets`.`pos` AS `pos`,`synsets`.`lexdomainid` AS `lexdomainid`,`synsets`.`definition` AS `definition`,`samplesets`.`sampleset` AS `sampleset` from ((((`words` left join `senses` `s` on(`words`.`wordid` = `s`.`wordid`)) left join `casedwords` on(`words`.`wordid` = `casedwords`.`wordid` and `s`.`casedwordid` = `casedwords`.`casedwordid`)) left join `synsets` on(`s`.`synsetid` = `synsets`.`synsetid`)) left join `samplesets` on(`s`.`synsetid` = `samplesets`.`synsetid`));

DROP VIEW IF EXISTS `morphology`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `morphology` AS select `morphmaps`.`morphid` AS `morphid`,`words`.`wordid` AS `wordid`,`words`.`lemma` AS `lemma`,`morphmaps`.`pos` AS `pos`,`morphs`.`morph` AS `morph` from ((`words` join `morphmaps` on(`words`.`wordid` = `morphmaps`.`wordid`)) join `morphs` on(`morphmaps`.`morphid` = `morphs`.`morphid`));

DROP VIEW IF EXISTS `sensesXsynsets`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `sensesXsynsets` AS select `senses`.`synsetid` AS `synsetid`,`senses`.`wordid` AS `wordid`,`senses`.`casedwordid` AS `casedwordid`,`senses`.`senseid` AS `senseid`,`senses`.`sensenum` AS `sensenum`,`senses`.`lexid` AS `lexid`,`senses`.`tagcount` AS `tagcount`,`senses`.`sensekey` AS `sensekey`,`synsets`.`pos` AS `pos`,`synsets`.`lexdomainid` AS `lexdomainid`,`synsets`.`definition` AS `definition` from (`senses` join `synsets` on(`senses`.`synsetid` = `synsets`.`synsetid`));

DROP VIEW IF EXISTS `sensesXlexlinksXsenses`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `sensesXlexlinksXsenses` AS select `l`.`linkid` AS `linkid`,`s`.`synsetid` AS `ssynsetid`,`s`.`wordid` AS `swordid`,`s`.`senseid` AS `ssenseid`,`s`.`casedwordid` AS `scasedwordid`,`s`.`sensenum` AS `ssensenum`,`s`.`lexid` AS `slexid`,`s`.`tagcount` AS `stagcount`,`s`.`sensekey` AS `ssensekey`,`s`.`pos` AS `spos`,`s`.`lexdomainid` AS `slexdomainid`,`s`.`definition` AS `sdefinition`,`d`.`synsetid` AS `dsynsetid`,`d`.`wordid` AS `dwordid`,`d`.`senseid` AS `dsenseid`,`d`.`casedwordid` AS `dcasedwordid`,`d`.`sensenum` AS `dsensenum`,`d`.`lexid` AS `dlexid`,`d`.`tagcount` AS `dtagcount`,`d`.`sensekey` AS `dsensekey`,`d`.`pos` AS `dpos`,`d`.`lexdomainid` AS `dlexdomainid`,`d`.`definition` AS `ddefinition` from ((`sensesXsynsets` `s` join `lexlinks` `l` on(`s`.`synsetid` = `l`.`synset1id` and `s`.`wordid` = `l`.`word1id`)) join `sensesXsynsets` `d` on(`l`.`synset2id` = `d`.`synsetid` and `l`.`word2id` = `d`.`wordid`));

DROP VIEW IF EXISTS `sensesXsemlinksXsenses`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `sensesXsemlinksXsenses` AS select `l`.`linkid` AS `linkid`,`s`.`synsetid` AS `ssynsetid`,`s`.`wordid` AS `swordid`,`s`.`senseid` AS `ssenseid`,`s`.`casedwordid` AS `scasedwordid`,`s`.`sensenum` AS `ssensenum`,`s`.`lexid` AS `slexid`,`s`.`tagcount` AS `stagcount`,`s`.`sensekey` AS `ssensekey`,`s`.`pos` AS `spos`,`s`.`lexdomainid` AS `slexdomainid`,`s`.`definition` AS `sdefinition`,`d`.`synsetid` AS `dsynsetid`,`d`.`wordid` AS `dwordid`,`d`.`senseid` AS `dsenseid`,`d`.`casedwordid` AS `dcasedwordid`,`d`.`sensenum` AS `dsensenum`,`d`.`lexid` AS `dlexid`,`d`.`tagcount` AS `dtagcount`,`d`.`sensekey` AS `dsensekey`,`d`.`pos` AS `dpos`,`d`.`lexdomainid` AS `dlexdomainid`,`d`.`definition` AS `ddefinition` from ((`sensesXsynsets` `s` join `semlinks` `l` on(`s`.`synsetid` = `l`.`synset1id`)) join `sensesXsynsets` `d` on(`l`.`synset2id` = `d`.`synsetid`));

DROP VIEW IF EXISTS `synsetsXsemlinksXsynsets`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `synsetsXsemlinksXsynsets` AS select `l`.`linkid` AS `linkid`,`s`.`synsetid` AS `ssynsetid`,`s`.`definition` AS `sdefinition`,`d`.`synsetid` AS `dsynsetid`,`d`.`definition` AS `ddefinition` from ((`synsets` `s` join `semlinks` `l` on(`s`.`synsetid` = `l`.`synset1id`)) join `synsets` `d` on(`l`.`synset2id` = `d`.`synsetid`));

DROP VIEW IF EXISTS `verbswithframes`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `verbswithframes` AS select `senses`.`synsetid` AS `synsetid`,`senses`.`wordid` AS `wordid`,`vframemaps`.`frameid` AS `frameid`,`senses`.`casedwordid` AS `casedwordid`,`senses`.`senseid` AS `senseid`,`senses`.`sensenum` AS `sensenum`,`senses`.`lexid` AS `lexid`,`senses`.`tagcount` AS `tagcount`,`senses`.`sensekey` AS `sensekey`,`vframes`.`frame` AS `frame`,`words`.`lemma` AS `lemma`,`synsets`.`pos` AS `pos`,`synsets`.`lexdomainid` AS `lexdomainid`,`synsets`.`definition` AS `definition` from ((((`senses` join `vframemaps` on(`senses`.`wordid` = `vframemaps`.`wordid` and `senses`.`synsetid` = `vframemaps`.`synsetid`)) join `vframes` on(`vframemaps`.`frameid` = `vframes`.`frameid`)) left join `words` on(`senses`.`wordid` = `words`.`wordid`)) left join `synsets` on(`senses`.`synsetid` = `synsets`.`synsetid`));

DROP VIEW IF EXISTS `wordsXsenses`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `wordsXsenses` AS select `words`.`wordid` AS `wordid`,`words`.`lemma` AS `lemma`,`senses`.`casedwordid` AS `casedwordid`,`senses`.`synsetid` AS `synsetid`,`senses`.`senseid` AS `senseid`,`senses`.`sensenum` AS `sensenum`,`senses`.`lexid` AS `lexid`,`senses`.`tagcount` AS `tagcount`,`senses`.`sensekey` AS `sensekey` from (`words` join `senses` on(`words`.`wordid` = `senses`.`wordid`));

DROP VIEW IF EXISTS `wordsXsensesXsynsets`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `wordsXsensesXsynsets` AS select `senses`.`synsetid` AS `synsetid`,`words`.`wordid` AS `wordid`,`words`.`lemma` AS `lemma`,`senses`.`casedwordid` AS `casedwordid`,`senses`.`senseid` AS `senseid`,`senses`.`sensenum` AS `sensenum`,`senses`.`lexid` AS `lexid`,`senses`.`tagcount` AS `tagcount`,`senses`.`sensekey` AS `sensekey`,`synsets`.`pos` AS `pos`,`synsets`.`lexdomainid` AS `lexdomainid`,`synsets`.`definition` AS `definition` from ((`words` join `senses` on(`words`.`wordid` = `senses`.`wordid`)) join `synsets` on(`senses`.`synsetid` = `synsets`.`synsetid`));


