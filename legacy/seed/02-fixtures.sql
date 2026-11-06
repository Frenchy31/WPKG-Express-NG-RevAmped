-- Fixtures designed to exercise every branch of the XML templates, plus escaping traps.
SET NAMES utf8;

INSERT INTO profiles (id, enabled, id_text, notes, created, modified) VALUES
 (1, 1, 'base',    'Socle commun', '2026-01-01 10:00:00', '2026-01-01 10:00:00'),
 (2, 1, 'office',  NULL,           '2026-01-02 10:00:00', '2026-01-02 10:00:00'),
 (3, 1, 'dev',     'Profil développeur', '2026-01-03 10:00:00', '2026-01-03 10:00:00'),
 (4, 0, 'retired', NULL,           '2026-01-04 10:00:00', '2026-01-04 10:00:00');

INSERT INTO profiles_profiles (profile_id, dependency_id) VALUES (3, 1), (2, 1);

INSERT INTO packages (id, name, id_text, enabled, revision, priority, reboot, execute, notify, notes, created, modified) VALUES
 (1,  '7-Zip',                  '7zip',          1, '23.01',          50,  0, 0, 0, 'Real package', '2026-02-01 10:00:00', '2026-02-01 10:00:00'),
 (2,  'All check types',        'all-checks',    1, '1.2.3',          100, 2, 2, 1, NULL,           '2026-02-02 10:00:00', '2026-02-02 10:00:00'),
 (3,  'Ampersand command',      'amp',           1, '1',              10,  0, 0, 0, NULL,           '2026-02-03 10:00:00', '2026-02-03 10:00:00'),
 (4,  'Ampersand and quote',    'amp-quote',     1, '1',              10,  0, 0, 0, NULL,           '2026-02-04 10:00:00', '2026-02-04 10:00:00'),
 (5,  'Angle brackets',         'angle',         1, '1',              10,  0, 0, 0, NULL,           '2026-02-05 10:00:00', '2026-02-05 10:00:00'),
 (6,  'Both quote kinds',       'both-quotes',   1, '1',              10,  0, 0, 0, NULL,           '2026-02-06 10:00:00', '2026-02-06 10:00:00'),
 (7,  'Disabled double dash',   'disabled-dash', 0, '1',              10,  0, 0, 0, NULL,           '2026-02-07 10:00:00', '2026-02-07 10:00:00'),
 (8,  'Variable revision',      'var-rev',       1, '%PKG_VERSION%',  5,   1, 1, 0, NULL,           '2026-02-08 10:00:00', '2026-02-08 10:00:00'),
 (9,  'Éditeur été',            'accent',        1, '2.0',            5,   0, 0, 0, 'Notes accentuées', '2026-02-09 10:00:00', '2026-02-09 10:00:00'),
 (10, 'Depends on others',      'dep-child',     1, '1.0',            0,   0, 0, 0, NULL,           '2026-02-10 10:00:00', '2026-02-10 10:00:00'),
 (11, 'Plain safe commands',    'plain',         1, '3.1',            0,   0, 0, 0, NULL,           '2026-02-11 10:00:00', '2026-02-11 10:00:00');

INSERT INTO packages_packages (package_id, dependency_id) VALUES (10, 1), (10, 2);

INSERT INTO packages_profiles (profile_id, package_id) VALUES
 (1, 1), (1, 11), (2, 2), (2, 9), (3, 8), (3, 10), (3, 3), (3, 7);

INSERT INTO package_actions (id, package_id, type, command, timeout, workdir, position) VALUES
 (1,  1, 0, '"%TEMP%\\7zip\\7zsetup.exe" /S',                       NULL, NULL,        1),
 (2,  1, 1, '"%TEMP%\\7zip\\7zsetup.exe" /S',                       NULL, NULL,        2),
 (3,  1, 3, '%ComSpec% /c "%ProgramFiles%\\7-Zip\\Uninstall.exe" /S', NULL, NULL,      3),
 (4,  2, 0, 'msiexec /i %SOFTWARE%\\all\\first.msi /qn',            600,  'C:\\Temp',  1),
 (5,  2, 0, 'msiexec /i %SOFTWARE%\\all\\second.msi /qn',           NULL, NULL,        2),
 (6,  2, 2, 'msiexec /i %SOFTWARE%\\all\\old.msi /qn',              1200, NULL,        3),
 (7,  2, 3, 'msiexec /x {11111111-2222-3333-4444-555555555555} /qn', NULL, NULL,       4),
 (8,  3, 0, 'cmd /c echo a & echo b',                               NULL, NULL,        1),
 (9,  4, 0, 'cmd /c "echo a & echo b"',                             NULL, NULL,        1),
 (10, 5, 0, 'cmd /c type foo.txt > bar.txt',                        NULL, NULL,        1),
 (11, 5, 3, 'cmd /c "type foo.txt > bar.txt"',                      NULL, NULL,        2),
 (12, 6, 0, 'cmd /c "echo it''s done"',                             NULL, NULL,        1),
 (13, 6, 3, 'cmd /c echo it''s done',                               NULL, NULL,        2),
 (14, 7, 0, 'setup.exe --silent --no-restart',                      NULL, NULL,        1),
 (15, 8, 0, 'setup.exe /version:%PKG_VERSION%',                     NULL, NULL,        1),
 (16, 9, 0, 'setup.exe /dir:"C:\\Program Files\\Été"',              NULL, NULL,        1),
 (17, 10, 0, 'setup.exe /q',                                        NULL, NULL,        1),
 (18, 11, 0, 'setup.exe /q',                                        NULL, NULL,        1),
 (19, 11, 1, 'upgrade.exe /q',                                      NULL, NULL,        2),
 (20, 11, 3, 'remove.exe /q',                                       NULL, NULL,        3);

INSERT INTO exit_codes (id, package_action_id, code, reboot) VALUES
 (1, 4, '0',    0),
 (2, 4, '3010', 1),
 (3, 4, '1641', 2),
 (4, 4, 'any',  3),
 (5, 5, '1',    0);

-- Tree layout: logical AND(1..16) > registry, OR(4..11) > file, NOT(7..10) > execute ; atleast/atmost siblings.
INSERT INTO package_checks (id, package_id, type, `condition`, path, value, parent_id, lft, rght) VALUES
 (1,  1, 3, 19, '7-Zip.*', NULL, NULL, 1, 2),
 (2,  2, 0, 1,  NULL, NULL, NULL, 3, 34),
 (3,  2, 1, 5,  'HKLM\\Software\\Vendor\\Key', NULL, 2, 4, 5),
 (4,  2, 1, 6,  'HKLM\\Software\\Vendor\\Version', '1.2.3', 2, 6, 7),
 (5,  2, 0, 2,  NULL, NULL, 2, 8, 23),
 (6,  2, 2, 7,  '%ProgramFiles%\\Vendor\\app.exe', NULL, 5, 9, 10),
 (7,  2, 2, 8,  '%ProgramFiles%\\Vendor\\app.exe', '1024', 5, 11, 12),
 (8,  2, 2, 9,  '%ProgramFiles%\\Vendor\\app.exe', '1.0', 5, 13, 14),
 (9,  2, 0, 0,  NULL, NULL, 5, 15, 22),
 (10, 2, 2, 10, '%ProgramFiles%\\Vendor\\app.exe', '1.1', 9, 16, 17),
 (11, 2, 2, 11, '%ProgramFiles%\\Vendor\\app.exe', '1.2', 9, 18, 19),
 (12, 2, 2, 12, '%ProgramFiles%\\Vendor\\app.exe', '1.3', 9, 20, 21),
 (13, 2, 2, 13, '%ProgramFiles%\\Vendor\\app.exe', '1.4', 2, 24, 25),
 (14, 2, 0, 3,  NULL, '2', 2, 26, 31),
 (15, 2, 4, 14, 'check.exe /a', '1', 14, 27, 28),
 (16, 2, 4, 15, 'check.exe /b', '2', 14, 29, 30),
 (17, 2, 0, 4,  NULL, '1', 2, 32, 33),
 (18, 2, 4, 16, 'check.exe /c', '0', NULL, 35, 36),
 (19, 2, 4, 17, 'check.exe /d', '3', NULL, 37, 38),
 (20, 2, 4, 18, 'check.exe /e', '4', NULL, 39, 40),
 (21, 2, 3, 20, 'Vendor App', '1.0', NULL, 41, 42),
 (22, 2, 3, 21, 'Vendor App', '1.1', NULL, 43, 44),
 (23, 2, 3, 22, 'Vendor App', '1.2', NULL, 45, 46),
 (24, 2, 3, 23, 'Vendor App', '1.3', NULL, 47, 48),
 (25, 2, 3, 24, 'Vendor App', '1.4', NULL, 49, 50),
 (26, 2, 5, 25, NULL, 'room1-pc.+', NULL, 51, 52),
 (27, 2, 5, 26, NULL, 'Windows 10', NULL, 53, 54),
 (28, 2, 5, 27, NULL, 'x64', NULL, 55, 56),
 (29, 2, 5, 28, NULL, 'FOO=bar', NULL, 57, 58);

INSERT INTO hosts (id, enabled, name, notes, created, modified, mainprofile_id, position) VALUES
 (1, 1, 'room1-pc.+',     NULL,        '2026-03-01 10:00:00', '2026-03-01 10:00:00', 1, 2),
 (2, 1, 'room2-pc.+',     'Salle 2',   '2026-03-02 10:00:00', '2026-03-02 10:00:00', 2, 1),
 (3, 0, 'old-pc.+',       NULL,        '2026-03-03 10:00:00', '2026-03-03 10:00:00', 1, 3),
 (4, 1, '^dev-[0-9]+$',   NULL,        '2026-03-04 10:00:00', '2026-03-04 10:00:00', 3, 4),
 (5, 1, '.+',             'Catch all', '2026-03-05 10:00:00', '2026-03-05 10:00:00', 1, 5);

INSERT INTO hosts_profiles (host_id, profile_id) VALUES (1, 3), (1, 2), (2, 4), (4, 2);

INSERT INTO variables (id, ref_id, ref_type, name, value) VALUES
 (1, 1, 0, 'PKG_VERSION', '23.01'),
 (2, 8, 0, 'PKG_VERSION', '4.5.6'),
 (3, 9, 0, 'LIBELLE',     'Été & hiver'),
 (4, 1, 1, 'SOFTWARE',    '\\\\server\\wpkg\\software'),
 (5, 3, 1, 'DEV',         '1'),
 (6, 1, 2, 'ROOM',        'room1'),
 (7, 2, 2, 'ROOM',        'room2');
