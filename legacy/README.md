# Pile de référence PHP 5.6

Pile figée de l'application d'origine. Elle sert uniquement à capturer les flux XML de référence
utilisés par `GoldenFeedTest`. Ne jamais la déployer.

```shell
git clone https://github.com/wpkg/wpkg-express-ng wpkg-express-ng
git -C wpkg-express-ng checkout 5947b62
docker compose up -d --build
./capture-golden.sh
docker compose down
```
