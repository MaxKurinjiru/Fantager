To je vlastně poměrně dobrá výchozí pozice — jako živnostník už máš rámec pro příjmy a nemusíš řešit některé věci, které by řešil člověk „mimo podnikání“. Zároveň ale platí, že označení „dar“ samo o sobě nerozhoduje; důležitý je **skutečný charakter toho, co hráči dostanou**.

Pro tvůj konkrétní model bych se díval hlavně na toto:

## 1) Nejbezpečnější nastavení: „podpora projektu + poděkování“

Např.:

* hráč dobrovolně přispěje na provoz hry
* ty mu jako poděkování aktivuješ kosmetický status

Formulace:

> „Podpora pomáhá pokrýt náklady na provoz a vývoj hry. Podporovatelé mohou získat odznak jako poděkování.“

Ne:

> „Kupte si odznak podporovatele za 5 €.“

Druhá varianta už velmi připomíná prodej digitální služby.

---

## 2) Účetnictví / daně

Protože jsi OSVČ, počítal bych s tím, že to budeš chtít evidovat.

Prakticky:

* příjmy z podpory někam patří
* schovávat si přehledy plateb
* mít jasno, co je příjem z projektu

Pokud používáš např. platební platformu, budeš mít výpisy, což je dobré pro evidenci.

---

## 3) DPH — pozor na digitální služby

Tady je největší technická oblast.

Pokud by to bylo považováno za poskytování digitální služby spotřebitelům v EU, mohou nastoupit pravidla kolem DPH (zejména při přeshraničních platbách).

U malé hry bych proto:

* nezačínal složitým obchodem
* neprodával desítky digitálních položek
* držel se jednoduché podpory

Ale pokud začne chodit třeba stovky plateb z celé EU, stojí za to to probrat s účetní.

---

## 4) Podmínky hry

Doporučil bych přidat krátkou stránku:

**Podpora projektu**

* podpora je dobrovolná
* hra zůstává zdarma
* status podporovatele není herní výhoda
* příspěvek není platba za výhru, měnu ani konkurenční výhodu
* vyhrazuješ si možnost změnit vzhled odznaku apod.

Nemusí to být právnický román — jde hlavně o očekávání.

---

## 5) GDPR u podporovatelů

Ve tvé databázi bych ukládal minimum:

Např.:

```
user_id
supporter = true
support_date
```

Ne:

* celou historii plateb, pokud ji nepotřebuješ
* veřejné částky u jmen

A pokud uděláš stránku „Děkujeme podporovatelům“:

* checkbox při podpoře:
  „Souhlasím se zveřejněním přezdívky“

---

## 6) Co bych nedělal

U malé hry bych se vyhnul:

❌ „VIP účet“
❌ měsíční členství s výhodami
❌ loot boxům
❌ měně kupované za peníze
❌ reklamnímu tlaku ve hře

To už tě posouvá do mnohem složitější oblasti.

---

Pro tvůj případ bych nastavil něco jako:

**Podpora hry**

* 1× příspěvek
* částka libovolná
* odměna: kosmetický badge + poděkování
* žádný vliv na gameplay

To je z pohledu komunity i právních rizik asi nejčistší.

Ještě bych se zeptal na jednu věc: **jsou hráči převážně z ČR, nebo očekáváš lidi z celé EU/světa?** U plateb to může být poměrně zásadní rozdíl.
