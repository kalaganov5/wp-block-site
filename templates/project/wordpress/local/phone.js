#!/usr/bin/env node
/*
 * Открыть локальный сайт на телефоне: печатает адрес сайта в локальной сети и QR-код в терминале.
 *
 *   ACME_BIND=0.0.0.0 docker compose up -d     (в PowerShell: $env:ACME_BIND="0.0.0.0"; docker compose up -d)
 *   node wordpress/local/phone.js
 *
 * Телефон и компьютер — в одной сети Wi-Fi. По умолчанию (без ACME_BIND) сайт виден только с этого компьютера.
 * Адрес сайта в базе менять не нужно: wordpress/local/acme-local-host.php подставляет адрес запроса сам.
 * QR рисует пакет qrcode-terminal (скачивается через npx при первом запуске); без интернета — только адрес.
 */
const os = require('os');
const { spawnSync } = require('child_process');

const PORT = process.env.ACME_PORT || '8080';
// В Windows docker и npx — командные файлы (.cmd): без оболочки spawnSync их не находит.
const USE_SHELL = process.platform === 'win32';

// Адреса локальной сети (IPv4, не внутренние). Первым — Wi-Fi/Ethernet домашней сети; VPN (маска /32 или имя
// адаптера) и виртуальные адаптеры Docker/WSL/Hyper-V — в конце: телефон через них сайт не увидит.
const VPN_NAME = /vpn|amnezia|wireguard|\bwg\d|tun|tap|outline|tailscale|zerotier|utun/i;
const VIRTUAL_NAME = /vethernet|wsl|docker|vbox|virtual|vmware|hyper-v|br-|veth/i;

// Чем меньше число, тем вероятнее, что адрес доступен телефону.
function addressRank(address) {
  const isVpn = VPN_NAME.test(address.name) || address.netmask === '255.255.255.255';
  const isVirtual = VIRTUAL_NAME.test(address.name);
  const isHomeNetwork = /^192\.168\./.test(address.ip);
  return (isVpn ? 3 : 0) + (isVirtual ? 2 : 0) + (isHomeNetwork ? 0 : 1);
}

function lanAddresses() {
  return Object.entries(os.networkInterfaces())
    .flatMap(([name, addresses]) =>
      (addresses || [])
        .filter((address) => address.family === 'IPv4' && !address.internal)
        .map((address) => ({ name, ip: address.address, netmask: address.netmask })),
    )
    .sort((left, right) => addressRank(left) - addressRank(right));
}

const siteUrl = (address) => `http://${address.ip}:${PORT}/`;

function printAddresses(addresses) {
  console.log(`\nСайт для телефона: ${siteUrl(addresses[0])}   (${addresses[0].name})`);
  if (addresses.length <= 1) return;
  console.log(
    'Другие адреса: ' +
      addresses
        .slice(1)
        .map((address) => `${siteUrl(address)} (${address.name})`)
        .join(', '),
  );
}

// Проверка, что Docker слушает сеть, а не только localhost.
function warnIfLocalOnly() {
  const composePort = spawnSync('docker', ['compose', 'port', 'wordpress', '80'], {
    encoding: 'utf8',
    shell: USE_SHELL,
  });
  if (composePort.status !== 0 || !/^127\.0\.0\.1:/.test(composePort.stdout.trim())) return;
  console.log(
    `\nВНИМАНИЕ: сайт сейчас открыт только для этого компьютера (${composePort.stdout.trim()}).`,
  );
  console.log('Перезапустите с доступом из сети: ACME_BIND=0.0.0.0 docker compose up -d');
}

function printQrCode(url) {
  // Адрес передаётся через ввод: вне интерактивного терминала (задача VS Code, CI) qrcode-terminal читает его оттуда, а не из аргумента.
  const qrCode = spawnSync('npx', ['--yes', 'qrcode-terminal@0.12.0'], {
    input: url + '\n',
    stdio: ['pipe', 'inherit', 'inherit'],
    shell: USE_SHELL,
  });
  if (qrCode.status !== 0)
    console.log('(QR-код не нарисован: нет доступа к npm. Наберите адрес на телефоне вручную.)');
}

function printTroubleshooting() {
  console.log('\nНе открывается с телефона?');
  console.log('  - телефон и компьютер в одной Wi-Fi (не гостевой сети);');
  console.log(
    '  - VPN на компьютере часто отрезает локальную сеть: включите в нём «доступ к локальной сети» или выключите VPN;',
  );
  console.log(
    '  - брандмауэр Windows: разрешите Docker Desktop для частных сетей, а сеть Wi-Fi отметьте как частную.',
  );
}

function main() {
  const addresses = lanAddresses();
  if (!addresses.length) {
    console.error('Не найден адрес в локальной сети: компьютер не подключён к Wi-Fi или сети.');
    process.exit(1);
  }
  printAddresses(addresses);
  warnIfLocalOnly();
  printQrCode(siteUrl(addresses[0]));
  printTroubleshooting();
}

main();
