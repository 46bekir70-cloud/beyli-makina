<?php
/**
 * Ülke + telefon kodu listesi.
 *
 * Teklif formundaki telefon alanı ve kurumsal adres bölümü bu listeyi kullanır.
 * Her satır:  ISO kodu | uluslararası arama kodu | Türkçe ülke adı
 *
 * Bayrak simgesi ayrıca saklanmıyor; ISO kodundan otomatik üretiliyor
 * (bm_country_flag). Böylece liste kısa ve bakımı kolay kalıyor.
 *
 * Not: Yeni ülke eklemek gerekirse alt satırlara aynı biçimde yazmak yeterli.
 */

if (!function_exists('bm_countries')) {

/** Ham liste — ISO2|arama kodu|Türkçe ad */
function bm_country_raw() {
    return <<<'TXT'
AF|93|Afganistan
DE|49|Almanya
US|1|Amerika Birleşik Devletleri
AD|376|Andorra
AO|244|Angola
AI|1264|Anguilla
AG|1268|Antigua ve Barbuda
AR|54|Arjantin
AL|355|Arnavutluk
AW|297|Aruba
AU|61|Avustralya
AT|43|Avusturya
AZ|994|Azerbaycan
BS|1242|Bahamalar
BH|973|Bahreyn
BD|880|Bangladeş
BB|1246|Barbados
EH|212|Batı Sahra
BE|32|Belçika
BZ|501|Belize
BJ|229|Benin
BM|1441|Bermuda
BY|375|Belarus
AE|971|Birleşik Arap Emirlikleri
GB|44|Birleşik Krallık
BO|591|Bolivya
BA|387|Bosna-Hersek
BW|267|Botsvana
BR|55|Brezilya
BN|673|Brunei
BG|359|Bulgaristan
BF|226|Burkina Faso
BI|257|Burundi
BT|975|Butan
KY|1345|Cayman Adaları
DJ|253|Cibuti
GI|350|Cebelitarık
DZ|213|Cezayir
CX|61|Christmas Adası
CC|61|Cocos Adaları
CK|682|Cook Adaları
TD|235|Çad
CZ|420|Çekya
CN|86|Çin
DK|45|Danimarka
DO|1809|Dominik Cumhuriyeti
DM|1767|Dominika
EC|593|Ekvador
GQ|240|Ekvator Ginesi
SV|503|El Salvador
ID|62|Endonezya
ER|291|Eritre
AM|374|Ermenistan
EE|372|Estonya
SZ|268|Esvatini
ET|251|Etiyopya
FK|500|Falkland Adaları
MA|212|Fas
FO|298|Faroe Adaları
CI|225|Fildişi Sahili
PH|63|Filipinler
PS|970|Filistin
FI|358|Finlandiya
FR|33|Fransa
GF|594|Fransız Guyanası
PF|689|Fransız Polinezyası
GA|241|Gabon
GM|220|Gambiya
GH|233|Gana
GN|224|Gine
GW|245|Gine-Bissau
GD|1473|Grenada
GL|299|Grönland
GP|590|Guadeloupe
GU|1671|Guam
GT|502|Guatemala
GG|44|Guernsey
GY|592|Guyana
ZA|27|Güney Afrika
GS|500|Güney Georgia
KR|82|Güney Kore
SS|211|Güney Sudan
GE|995|Gürcistan
HT|509|Haiti
IN|91|Hindistan
HR|385|Hırvatistan
NL|31|Hollanda
HN|504|Honduras
HK|852|Hong Kong
IQ|964|Irak
IR|98|İran
IE|353|İrlanda
ES|34|İspanya
IL|972|İsrail
SE|46|İsveç
CH|41|İsviçre
IT|39|İtalya
IS|354|İzlanda
JM|1876|Jamaika
JP|81|Japonya
JE|44|Jersey
KH|855|Kamboçya
CM|237|Kamerun
CA|1|Kanada
ME|382|Karadağ
QA|974|Katar
KZ|7|Kazakistan
KE|254|Kenya
CY|357|Kıbrıs
KG|996|Kırgızistan
KI|686|Kiribati
CO|57|Kolombiya
KM|269|Komorlar
CG|242|Kongo Cumhuriyeti
CD|243|Kongo Demokratik Cumhuriyeti
XK|383|Kosova
CR|506|Kosta Rika
KW|965|Kuveyt
KP|850|Kuzey Kore
MK|389|Kuzey Makedonya
MP|1670|Kuzey Mariana Adaları
CU|53|Küba
LA|856|Laos
LS|266|Lesotho
LV|371|Letonya
LR|231|Liberya
LY|218|Libya
LI|423|Liechtenstein
LT|370|Litvanya
LB|961|Lübnan
LU|352|Lüksemburg
HU|36|Macaristan
MG|261|Madagaskar
MO|853|Makao
MW|265|Malavi
MV|960|Maldivler
MY|60|Malezya
ML|223|Mali
MT|356|Malta
IM|44|Man Adası
MH|692|Marshall Adaları
MQ|596|Martinik
MU|230|Mauritius
YT|262|Mayotte
MX|52|Meksika
EG|20|Mısır
FM|691|Mikronezya
MN|976|Moğolistan
MD|373|Moldova
MC|377|Monako
MS|1664|Montserrat
MR|222|Moritanya
MZ|258|Mozambik
MM|95|Myanmar
NA|264|Namibya
NR|674|Nauru
NP|977|Nepal
NE|227|Nijer
NG|234|Nijerya
NI|505|Nikaragua
NU|683|Niue
NF|672|Norfolk Adası
NO|47|Norveç
CF|236|Orta Afrika Cumhuriyeti
UZ|998|Özbekistan
PK|92|Pakistan
PW|680|Palau
PA|507|Panama
PG|675|Papua Yeni Gine
PY|595|Paraguay
PE|51|Peru
PL|48|Polonya
PT|351|Portekiz
PR|1787|Porto Riko
RE|262|Reunion
RO|40|Romanya
RW|250|Ruanda
RU|7|Rusya
WS|685|Samoa
SM|378|San Marino
ST|239|Sao Tome ve Principe
SN|221|Senegal
SC|248|Seyşeller
RS|381|Sırbistan
SL|232|Sierra Leone
SG|65|Singapur
SK|421|Slovakya
SI|386|Slovenya
SB|677|Solomon Adaları
SO|252|Somali
LK|94|Sri Lanka
SD|249|Sudan
SR|597|Surinam
SY|963|Suriye
SA|966|Suudi Arabistan
SH|290|Saint Helena
KN|1869|Saint Kitts ve Nevis
LC|1758|Saint Lucia
PM|508|Saint Pierre ve Miquelon
VC|1784|Saint Vincent ve Grenadinler
CL|56|Şili
TJ|992|Tacikistan
TZ|255|Tanzanya
TH|66|Tayland
TW|886|Tayvan
TG|228|Togo
TK|690|Tokelau
TO|676|Tonga
TT|1868|Trinidad ve Tobago
TN|216|Tunus
TC|1649|Turks ve Caicos Adaları
TR|90|Türkiye
TM|993|Türkmenistan
TV|688|Tuvalu
UG|256|Uganda
UA|380|Ukrayna
OM|968|Umman
UY|598|Uruguay
JO|962|Ürdün
VU|678|Vanuatu
VA|379|Vatikan
VE|58|Venezuela
VN|84|Vietnam
VG|1284|Virjin Adaları (İngiliz)
VI|1340|Virjin Adaları (ABD)
WF|681|Wallis ve Futuna
YE|967|Yemen
NC|687|Yeni Kaledonya
NZ|64|Yeni Zelanda
GR|30|Yunanistan
ZM|260|Zambiya
ZW|263|Zimbabve
TXT;
}

/**
 * Listeyi diziye çevirir: [ ['iso'=>'TR','dial'=>'90','name'=>'Türkiye'], ... ]
 * Sonuç bir kez hesaplanıp saklanır (her çağrıda yeniden ayrıştırılmaz).
 */
function bm_countries() {
    static $liste = null;
    if ($liste !== null) return $liste;

    $liste = [];
    foreach (explode("\n", bm_country_raw()) as $satir) {
        $satir = trim($satir);
        if ($satir === '') continue;
        $p = explode('|', $satir);
        if (count($p) < 3) continue;
        $liste[] = [
            'iso'  => strtoupper(trim($p[0])),
            'dial' => preg_replace('/\D/', '', $p[1]),
            'name' => trim($p[2]),
        ];
    }
    return $liste;
}

/** ISO koduna göre tek ülke döndürür (bulunamazsa null) */
function bm_country($iso) {
    $iso = strtoupper(trim((string)$iso));
    foreach (bm_countries() as $c) {
        if ($c['iso'] === $iso) return $c;
    }
    return null;
}

/**
 * ISO kodundan bayrak simgesi üretir (TR -> 🇹🇷).
 * Harfler Unicode "bölgesel gösterge" karakterlerine kaydırılıyor.
 */
function bm_country_flag($iso) {
    $iso = strtoupper(trim((string)$iso));
    if (!preg_match('/^[A-Z]{2}$/', $iso)) return '';
    $bayrak = '';
    for ($i = 0; $i < 2; $i++) {
        $kod = 0x1F1E6 + (ord($iso[$i]) - 65);
        $bayrak .= mb_convert_encoding('&#' . $kod . ';', 'UTF-8', 'HTML-ENTITIES');
    }
    return $bayrak;
}

} // function_exists
