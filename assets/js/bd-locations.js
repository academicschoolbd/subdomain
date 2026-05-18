/* ===========================================================================
   institution.bd — Bangladesh location cascade
   v3.2 — used by /claim profile gate + institution-details form, and by
   /dashboard's account form. Ships a Division → District → Upazila dataset
   plus `BDLocations.bind(div, dist, upa, initial?)` that wires three native
   <select> elements into a cascade.
   =========================================================================== */
(function (root) {
  'use strict';

  /* Source: Bangladesh Bureau of Statistics — 8 divisions, 64 districts, ~495
     upazilas. Names use BBS English transliteration so they round-trip with
     the existing free-text values that may already be saved on user rows. */
  const DATA = {
    'Dhaka': {
      'Dhaka':        ['Dhanmondi','Gulshan','Mirpur','Mohammadpur','Tejgaon','Ramna','Motijheel','Khilgaon','Pallabi','Sutrapur','Demra','Lalbagh','Kotwali','Hazaribagh','Cantonment','Badda','Uttara','Savar','Dhamrai','Keraniganj','Nawabganj','Dohar'],
      'Faridpur':     ['Alfadanga','Bhanga','Boalmari','Charbhadrasan','Faridpur Sadar','Madhukhali','Nagarkanda','Sadarpur','Saltha'],
      'Gazipur':      ['Gazipur Sadar','Kaliakair','Kaliganj','Kapasia','Sreepur'],
      'Gopalganj':    ['Gopalganj Sadar','Kashiani','Kotalipara','Muksudpur','Tungipara'],
      'Kishoreganj':  ['Astagram','Bajitpur','Bhairab','Hossainpur','Itna','Karimganj','Katiadi','Kishoreganj Sadar','Kuliarchar','Mithamain','Nikli','Pakundia','Tarail'],
      'Madaripur':    ['Kalkini','Madaripur Sadar','Rajoir','Shibchar'],
      'Manikganj':    ['Daulatpur','Ghior','Harirampur','Manikganj Sadar','Saturia','Shibalaya','Singair'],
      'Munshiganj':   ['Gazaria','Lohajang','Munshiganj Sadar','Sirajdikhan','Sreenagar','Tongibari'],
      'Narayanganj':  ['Araihazar','Bandar','Narayanganj Sadar','Rupganj','Sonargaon'],
      'Narsingdi':    ['Belabo','Monohardi','Narsingdi Sadar','Palash','Raipura','Shibpur'],
      'Rajbari':      ['Baliakandi','Goalandaghat','Kalukhali','Pangsha','Rajbari Sadar'],
      'Shariatpur':   ['Bhedarganj','Damudya','Gosairhat','Naria','Shariatpur Sadar','Zajira'],
      'Tangail':      ['Basail','Bhuapur','Delduar','Dhanbari','Ghatail','Gopalpur','Kalihati','Madhupur','Mirzapur','Nagarpur','Sakhipur','Tangail Sadar'],
    },
    'Chattogram': {
      'Bandarban':    ['Alikadam','Bandarban Sadar','Lama','Naikhongchhari','Rowangchhari','Ruma','Thanchi'],
      'Brahmanbaria': ['Akhaura','Ashuganj','Bancharampur','Bijoynagar','Brahmanbaria Sadar','Kasba','Nabinagar','Nasirnagar','Sarail'],
      'Chandpur':     ['Chandpur Sadar','Faridganj','Haimchar','Haziganj','Kachua','Matlab Dakshin','Matlab Uttar','Shahrasti'],
      'Chattogram':   ['Anwara','Banshkhali','Boalkhali','Chandanaish','Fatikchhari','Hathazari','Karnaphuli','Lohagara','Mirsharai','Patiya','Rangunia','Raozan','Sandwip','Satkania','Sitakunda','Chattogram Sadar'],
      'Cox\'s Bazar': ["Chakaria","Cox's Bazar Sadar","Kutubdia","Maheshkhali","Pekua","Ramu","Teknaf","Ukhia"],
      'Cumilla':      ['Barura','Brahmanpara','Burichang','Chandina','Chauddagram','Cumilla Adarsha Sadar','Cumilla Sadar Dakshin','Daudkandi','Debidwar','Homna','Laksam','Lalmai','Manoharganj','Meghna','Muradnagar','Nangalkot','Titas'],
      'Feni':         ['Chhagalnaiya','Daganbhuiyan','Feni Sadar','Fulgazi','Parshuram','Sonagazi'],
      'Khagrachhari': ['Dighinala','Khagrachhari Sadar','Lakshmichhari','Mahalchhari','Manikchhari','Matiranga','Panchhari','Ramgarh'],
      'Lakshmipur':   ['Kamalnagar','Lakshmipur Sadar','Raipur','Ramganj','Ramgati'],
      'Noakhali':     ['Begumganj','Chatkhil','Companiganj','Hatiya','Kabirhat','Noakhali Sadar','Senbagh','Sonaimuri','Subarnachar'],
      'Rangamati':    ['Baghaichhari','Barkal','Belaichhari','Juraichhari','Kaptai','Kawkhali','Langadu','Naniarchar','Rajasthali','Rangamati Sadar'],
    },
    'Khulna': {
      'Bagerhat':     ['Bagerhat Sadar','Chitalmari','Fakirhat','Kachua','Mollahat','Mongla','Morrelganj','Rampal','Sarankhola'],
      'Chuadanga':    ['Alamdanga','Chuadanga Sadar','Damurhuda','Jibannagar'],
      'Jessore':      ['Abhaynagar','Bagherpara','Chaugachha','Jhikargachha','Jessore Sadar','Keshabpur','Manirampur','Sharsha'],
      'Jhenaidah':    ['Harinakunda','Jhenaidah Sadar','Kaliganj','Kotchandpur','Maheshpur','Shailkupa'],
      'Khulna':       ['Batiaghata','Dacope','Dighalia','Dumuria','Koyra','Paikgachha','Phultala','Rupsha','Terokhada','Khulna Sadar','Khalishpur','Sonadanga','Daulatpur','Khan Jahan Ali'],
      'Kushtia':      ['Bheramara','Daulatpur','Khoksa','Kumarkhali','Kushtia Sadar','Mirpur'],
      'Magura':       ['Magura Sadar','Mohammadpur','Shalikha','Sreepur'],
      'Meherpur':     ['Gangni','Meherpur Sadar','Mujibnagar'],
      'Narail':       ['Kalia','Lohagara','Narail Sadar'],
      'Satkhira':     ['Assasuni','Debhata','Kalaroa','Kaliganj','Satkhira Sadar','Shyamnagar','Tala'],
    },
    'Rajshahi': {
      'Bogura':       ['Adamdighi','Bogura Sadar','Dhunat','Dhupchanchia','Gabtali','Kahaloo','Nandigram','Sariakandi','Shajahanpur','Sherpur','Shibganj','Sonatala'],
      'Chapainawabganj': ['Bholahat','Chapainawabganj Sadar','Gomastapur','Nachole','Shibganj'],
      'Joypurhat':    ['Akkelpur','Joypurhat Sadar','Kalai','Khetlal','Panchbibi'],
      'Naogaon':      ['Atrai','Badalgachhi','Dhamoirhat','Manda','Mohadevpur','Naogaon Sadar','Niamatpur','Patnitala','Porsha','Raninagar','Sapahar'],
      'Natore':       ['Bagatipara','Baraigram','Gurudaspur','Lalpur','Naldanga','Natore Sadar','Singra'],
      'Pabna':        ['Atgharia','Bera','Bhangura','Chatmohar','Faridpur','Ishwardi','Pabna Sadar','Santhia','Sujanagar'],
      'Rajshahi':     ['Bagha','Bagmara','Charghat','Durgapur','Godagari','Mohanpur','Paba','Puthia','Tanore','Boalia','Motihar','Rajpara','Shah Makhdum'],
      'Sirajganj':    ['Belkuchi','Chauhali','Kamarkhanda','Kazipur','Raiganj','Shahjadpur','Sirajganj Sadar','Tarash','Ullapara'],
    },
    'Rangpur': {
      'Dinajpur':     ['Birampur','Birganj','Birol','Bochaganj','Chirirbandar','Dinajpur Sadar','Fulbari','Ghoraghat','Hakimpur','Kaharole','Khansama','Nawabganj','Parbatipur'],
      'Gaibandha':    ['Fulchhari','Gaibandha Sadar','Gobindaganj','Palashbari','Sadullapur','Saghata','Sundarganj'],
      'Kurigram':     ['Bhurungamari','Char Rajibpur','Chilmari','Kurigram Sadar','Nageshwari','Phulbari','Rajarhat','Raomari','Ulipur'],
      'Lalmonirhat':  ['Aditmari','Hatibandha','Kaliganj','Lalmonirhat Sadar','Patgram'],
      'Nilphamari':   ['Dimla','Domar','Jaldhaka','Kishoreganj','Nilphamari Sadar','Saidpur'],
      'Panchagarh':   ['Atwari','Boda','Debiganj','Panchagarh Sadar','Tetulia'],
      'Rangpur':      ['Badarganj','Gangachara','Kaunia','Mithapukur','Pirgachha','Pirganj','Rangpur Sadar','Taraganj'],
      'Thakurgaon':   ['Baliadangi','Haripur','Pirganj','Ranisankail','Thakurgaon Sadar'],
    },
    'Sylhet': {
      'Habiganj':     ['Ajmiriganj','Bahubal','Baniachong','Chunarughat','Habiganj Sadar','Lakhai','Madhabpur','Nabiganj','Shayestaganj'],
      'Moulvibazar':  ['Barlekha','Juri','Kamalganj','Kulaura','Moulvibazar Sadar','Rajnagar','Sreemangal'],
      'Sunamganj':    ['Bishwamvarpur','Chhatak','Derai','Dharampasha','Dhirai','Doarabazar','Jagannathpur','Jamalganj','Sullah','Sunamganj Sadar','Tahirpur'],
      'Sylhet':       ['Balaganj','Beanibazar','Bishwanath','Companiganj','Dakshin Surma','Fenchuganj','Golapganj','Gowainghat','Jaintiapur','Kanaighat','Sylhet Sadar','Zakiganj','Osmani Nagar'],
    },
    'Mymensingh': {
      'Jamalpur':     ['Bakshiganj','Dewanganj','Islampur','Jamalpur Sadar','Madarganj','Melandaha','Sarishabari'],
      'Mymensingh':   ['Bhaluka','Dhobaura','Fulbaria','Gaffargaon','Gauripur','Haluaghat','Ishwarganj','Muktagachha','Mymensingh Sadar','Nandail','Phulpur','Trishal','Tarakanda'],
      'Netrokona':    ['Atpara','Barhatta','Durgapur','Kalmakanda','Kendua','Khaliajuri','Madan','Mohanganj','Netrokona Sadar','Purbadhala'],
      'Sherpur':      ['Jhenaigati','Nakla','Nalitabari','Sherpur Sadar','Sreebardi'],
    },
    'Barishal': {
      'Barguna':      ['Amtali','Bamna','Barguna Sadar','Betagi','Patharghata','Taltali'],
      'Barishal':     ['Agailjhara','Babuganj','Bakerganj','Banaripara','Barishal Sadar','Gournadi','Hizla','Mehendiganj','Muladi','Wazirpur'],
      'Bhola':        ['Bhola Sadar','Burhanuddin','Char Fasson','Daulatkhan','Lalmohan','Manpura','Tazumuddin'],
      'Jhalokati':    ['Jhalokati Sadar','Kathalia','Nalchity','Rajapur'],
      'Patuakhali':   ['Bauphal','Dashmina','Dumki','Galachipa','Kalapara','Mirzaganj','Patuakhali Sadar','Rangabali'],
      'Pirojpur':     ['Bhandaria','Kawkhali','Mathbaria','Nazirpur','Nesarabad','Pirojpur Sadar','Zianagar'],
    },
  };

  function divisions() { return Object.keys(DATA); }
  function districts(div) { return Object.keys(DATA[div] || {}); }
  function upazilas(div, dist) { return (DATA[div] && DATA[div][dist]) || []; }

  /** Populate a <select> with a placeholder + the given list. Preserves the
   *  previously-selected value as an "Other" option if it isn't in the list,
   *  so users with legacy free-text values don't lose their data. */
  function fill(sel, items, placeholder, keepValue) {
    if (!sel) return;
    const want = keepValue || sel.value || '';
    sel.innerHTML = '';
    const ph = document.createElement('option');
    ph.value = '';
    ph.textContent = placeholder || '— Select —';
    sel.appendChild(ph);
    items.forEach((label) => {
      const o = document.createElement('option');
      o.value = label; o.textContent = label;
      sel.appendChild(o);
    });
    // If the saved value isn't in the list, keep it as a sentinel option so
    // the user can still see what's stored without losing it on first save.
    if (want && items.indexOf(want) === -1) {
      const o = document.createElement('option');
      o.value = want; o.textContent = want + ' (saved)';
      sel.appendChild(o);
    }
    sel.value = want;
  }

  /** Wire three <select> elements (or a single <select> for division-only).
   *  Pass an `initial = { division, district, upazila }` map to restore the
   *  saved values; all three are independently optional. */
  function bind(divEl, distEl, upaEl, initial) {
    initial = initial || {};
    fill(divEl, divisions(), '— Select division —', initial.division || '');
    const refreshDistrict = () => {
      const d = (divEl && divEl.value) || '';
      fill(distEl, districts(d), '— Select district —',
           (distEl && (distEl.dataset._initial || '')) || '');
      refreshUpazila();
    };
    const refreshUpazila = () => {
      const d = (divEl && divEl.value) || '';
      const di = (distEl && distEl.value) || '';
      fill(upaEl, upazilas(d, di), '— Select upazila —',
           (upaEl && (upaEl.dataset._initial || '')) || '');
    };

    // Stash the initial district/upazila once so the first cascade doesn't
    // wipe them. After the first user interaction these dataset hints are
    // cleared and the cascade behaves normally.
    if (distEl) distEl.dataset._initial = initial.district || '';
    if (upaEl)  upaEl.dataset._initial  = initial.upazila || '';

    if (divEl) {
      divEl.addEventListener('change', () => {
        if (distEl) distEl.dataset._initial = '';
        if (upaEl)  upaEl.dataset._initial  = '';
        refreshDistrict();
      });
    }
    if (distEl) {
      distEl.addEventListener('change', () => {
        if (upaEl) upaEl.dataset._initial = '';
        refreshUpazila();
      });
    }

    refreshDistrict();
  }

  root.BDLocations = { DATA, divisions, districts, upazilas, bind };
})(window);
