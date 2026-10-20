<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

/**
 * Current menu of Antico Burger: `php artisan db:seed --class=AnticoBurgerMenuSeeder --force`.
 *
 * Idempotent: categories are matched by name and products by category + name;
 * prices and ingredients are updated, photos and the "special" flag are never touched.
 * An asterisk marks products frozen at origin, as on the printed menu.
 */
class AnticoBurgerMenuSeeder extends Seeder
{
    private const BEER_RADEBERGER = "Birra prodotta in Germania secondo l'Editto della Purezza tedesco del 1516. Il colore è di un giallo paglierino, il gusto è secco, pulito con le accentuate note d'amaro tipiche della pils.\nGrado alcolico: 5,0% - Stile: Pils";

    private const BEER_HEFEWEIZEN_BOCK = "Birra doppio malto di frumento, bionda non filtrata, con schiuma bianca fine e persistente. Aroma ben strutturato con sentori di mela verde, banana, ciliegia, farina d'orzo e note dolci finali. Ottima con arrosto, wurstel e verdure.\nGrado alcolico: 7,2% - Stile: Weissbock - IBU: 14";

    private const BEER_CORSENDONK_ROUSSE = "Questa birra è prodotta dalla birreria artigianale belga Corsendonk, con materie prime accuratamente selezionate. Il colore è ambrato limpido e vivace, al palato è speziata, con sentori di caramello e di frutta.\nGrado alcolico: 8,0% - Stile: Belgian Strong Ale";

    private const BEER_FILOU = "La Filou (imbrogliona) è una tripel di carattere ma silente, infatti, nonostante il suo grado alcolico, si beve facilmente. Prodotta con luppoli aromatici belgi, cechi e di malto lager e rifermentata in bottiglia.\nGrado alcolico: 8,5% - Stile: Tripel";

    /**
     * Category name => list of [name, price, ingredients|null], in menu order.
     */
    private const MENU = [
        'Antipasti' => [
            ['Patata Really Crunchy*', '5.50', null],
            ['Patata Dippers*', '6.50', 'Patata dippers cacio e pepe.'],
            ['Bocconcini', '6.50', 'Bocconcini di provola affumicata.'],
            ['Nuggets', '7.00', null],
            ['Alette di pollo', '7.00', 'Salsa agrodolce.'],
            ['Polpettine pulled', '7.00', 'Salsa agrodolce.'],
        ],
        'Panini' => [
            ['Ispanico', '12.50', 'Hamburger di Black Angus Spagna da 180 gr, crema di patate arrosto, burratina pugliese, pancetta al pepe, salsa al pepe e lime.'],
            ['Made in Antico', '12.50', 'Hamburger black angus 180 gr, prosciutto cotto, patate al forno, provola e salsa maisteriosa.'],
            ['Antico', '12.00', 'Hamburger di salsiccia di maiale a punta di coltello da 180 gr, provola, friarielli e salsa Antico.'],
            ["Tu vuo' fa l'americano", '11.50', 'Hamburger di Black Angus 180 gr, formaggio Monterey Jack, rosti di patate*, cipolla al burro e salsa senape al miele.'],
            ['Poll-Gin', '11.50', 'Sovracoscia di pollo impanata ai cornflakes, crema di piselli, stracciata di bufala, patate fresche al forno e salsa gin lemon.'],
            ['Classicum', '11.50', 'Porchetta selezione Antico, provola, patate fresche al forno e maionese al rosmarino.'],
            ['Campagnolo', '12.50', 'Hamburger di black angus da 180 gr, emmental, melanzane a filetto, capocollo e maionese al miele e peperoncino.'],
            ["Trinita'", '11.50', 'Hamburger di salsiccia di maiale a punta di coltello 180 gr, provola fritta nei cornflakes, fagioli alla messicana e salsa aromatizzata alla cipolla.'],
            ['Il Boscaiolo', '12.00', 'Hamburger di salsiccia di maiale a punta di coltello 180 gr, funghi champignons saltati in padella, rucola e formaggio caprino e salsa antico.'],
            ['Carciofo Ribelle (Vegetariano)', '11.50', 'Rosti di patate*, carciofi* in doppia consistenza, Feta greca e salsa antico.'],
            ['Semplice', '10.50', 'Hamburger di black angus da 180 gr, pomodori, insalata, provola e maionese.'],
        ],
        'Dolci' => [
            ["Tiramisu'", '6.00', null],
            ['Cheesecake', '6.00', null],
            ['Cuore caldo', '6.00', null],
        ],
        'Distillati' => [
            ['Vari', '3.00', null],
        ],
        'Bibite' => [
            ['Acqua Mood (cl 50)', '1.50', null],
            ['Coca Cola (cl 33)', '3.00', null],
            ['Coca Cola Zero (cl 33)', '3.00', null],
            ['Sprite (cl 33)', '3.00', null],
            ['Fanta (cl 33)', '3.00', null],
        ],
        'Birre alla spina' => [
            ['Radeberger (cl 33)', '5.00', self::BEER_RADEBERGER],
            ['Radeberger (cl 50)', '6.50', self::BEER_RADEBERGER],
            ['Norbertus Hefeweizen Bock (cl 33)', '5.50', self::BEER_HEFEWEIZEN_BOCK],
            ['Norbertus Hefeweizen Bock (cl 50)', '7.00', self::BEER_HEFEWEIZEN_BOCK],
            ['Corsendonk Rousse (cl 33)', '6.00', self::BEER_CORSENDONK_ROUSSE],
            ['Corsendonk Rousse (cl 50)', '8.50', self::BEER_CORSENDONK_ROUSSE],
            ['Filou (cl 33)', '6.00', self::BEER_FILOU],
            ['Filou (cl 50)', '7.50', self::BEER_FILOU],
        ],
        'Birre in bottiglia' => [
            ['Caulier 28 Gluten Free blonde (cl 33)', '6.00', "La schiuma è bianca, molto fine, compatta e persistente. Il colore è giallo dorato. Al naso si presenta intensa e persistente con note fruttate e finale speziato. Al palato ha un ingresso dolce che tende verso un leggero e piacevole amaro. La secchezza finale la rende molto beverina.\nGrado alcolico: 6,8% - Stile: Belgian Ale\nIndice glicemico < 2 / Glutine < 20 ppm / Calorie (100 ml) 48 kcal"],
            ['Duck (cl 33)', '7.00', "Strong Ale scura, profumata e luppolata. Birra di grande carattere, luppoli americani, tedeschi, malti caramello e speziatura di coriandolo a fine bollitura, rendono la Duck \"pericolosamente beverina\". Piccolo omaggio agli anziani del paese che chiamavano la sbornia \"nana\" (ovvero anatra in toscano).\nGrado alcolico: 8% - Stile: Strong Ale - Fermentazione: alta - Colore: rossa scura"],
            ['Clausthaler original alcool free (cl 33)', '5.00', "Bionda giallo dorato con schiuma bianca, fine e persistente. Aroma fresco, fine, floreale con note amarognole e gusto amaro cremoso, ampio, dolce di frutta, note di luppolo e di spezie. Ben equilibrato.\nGrado alcolico: < 0,49% - Stile: Pils - Tipo di fermentazione: bassa - Colore: giallo dorato"],
            ['Norbertus Kardinal (cl 50)', '7.50', "Birra ambrata con schiuma bianca fine. Aroma persistente con ampi sentori di lieviti, fieno, frutta molto matura, prugna californiana con note speziate. Ottima con salumi, frittate e carni.\nGrado alcolico: 7,5% - Stile: Dunkel Bock - IBU: 26"],
            ['Norbertus Edelstark (cl 50)', '7.50', "Birra Tedesca dal colore giallo dorato, limpida con schiuma bianca fine e persistente. Aroma di lieviti intensi, frutta, pera, mela e scorza di arancia con sentori floreali e speziati. Gusto intenso, ben strutturato e equilibrato.\nGrado alcolico: 7,5% - Stile: Bock (doppio malto)"],
            ['Bk (cl 33)', '7.00', "Stout vera con massiccio uso di malti tostati e luppolatura muscolosa. Sentori di caffè, liquirizia e cioccolata amara lasciano poi spazio all'erbaceo persistente del luppolo. Una irlandese in Toscana!\nGrado alcolico: 6% - Stile: Stout"],
            ['Kasteel triple (cl 33)', '7.00', "Dal colore chiaro velato questa birra belga rilascia nell'aria il profumo di malto, lievito e brandy. Il gusto è avvolgente e inebriante.\nGrado alcolico: 11,0% - Stile: Strong Ale"],
            ['Valchiria (cl 33)', '6.00', "Birra di ispirazione anglo-americana si presenta con una struttura robusta e un amaro lungo e deciso. La complessità dei malti utilizzati viene bilanciata dall'eleganza dei luppoli americani. I sentori agrumati e floreali dei luppoli rendono questa birra estremamente piacevole alla bevuta. Tipo di fermentazione: alta. Colore: Ramato Scuro.\nGrado alcolico: 8% - Stile: Double IPA"],
            ['Hasen Hell (cl 50)', '5.50', "Aroma maltato, note di miele d'acacia e albicocca disidratata. Gusto equilibrato, cremoso, con una lieve luppolatura e una persistenza prolungata. Fermentazione: bassa. Colore: Biondo Dorato.\nGrado alcolico: 5% - Stile: Hell"],
            ['Corsendonk Blanche (cl 33)', '6.00', "La Corsendonk Blanche è una Birra bianca di frumento, non filtrata, prodotta in Belgio con materie prime accuratamente selezionate. È una Birra d'Abbazia, dal colore oro opalescente e dal profumo classico di lievito. Ha un sapore intenso classico dello stile Blanche, agrumato, con un amaro ben equilibrato e sentori di arancia, limone e spezie.\nGrado alcolico: 4,5% - Stile: Bianca"],
        ],
        'Extra' => [
            ['Coperto', '2.00', null],
            ['Aggiunta di carne', '3.00', null],
            ['Aggiunta di bacon', '2.50', null],
            ['Aggiunta di uovo', '2.50', null],
        ],
    ];

    public function run(): void
    {
        $sortOrder = 0;

        foreach (self::MENU as $categoryName => $items) {
            $category = MenuCategory::query()->updateOrCreate(['name' => $categoryName], ['sort_order' => ++$sortOrder]);

            foreach ($items as [$name, $price, $ingredients]) {
                MenuItem::query()->updateOrCreate(
                    ['menu_category_id' => $category->id, 'name' => $name],
                    ['price' => $price, 'ingredients' => $ingredients],
                );
            }
        }
    }
}
