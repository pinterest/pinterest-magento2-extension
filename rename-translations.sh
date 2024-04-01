#!/usr/bin/env bash

declare -A map

map["i18n/af.csv"]="i18n/af_ZA.csv"
map["i18n/ar.csv"]="i18n/ar_EG.csv"
map["i18n/bg.csv"]="i18n/bg_BG.csv" 
map["i18n/bn.csv"]="i18n/bn_BD.csv"
map["i18n/cs.csv"]="i18n/cs_CZ.csv"
map["i18n/da.csv"]="i18n/da_DK.csv"
map["i18n/de.csv"]="i18n/de_DE.csv"
map["i18n/el.csv"]="i18n/el_GR.csv"
map["i18n/en_GB.csv"]="i18n/en_GB.csv"
map["i18n/en_US.csv"]="i18n/en_US.csv"
map["i18n/es.csv"]="i18n/es_ES.csv"
map["i18n/es_MX.csv"]="i18n/es_MX.csv"
map["i18n/fi.csv"]="i18n/fi_FI.csv"
map["i18n/fr.csv"]="i18n/fr_FR.csv"
map["i18n/he.csv"]="i18n/he_IL.csv"
map["i18n/hi.csv"]="i18n/hi_IN.csv"
map["i18n/hr.csv"]="i18n/hr_HR.csv"
map["i18n/hu.csv"]="i18n/hu_HU.csv"
map["i18n/id.csv"]="i18n/id_ID.csv"
map["i18n/it.csv"]="i18n/it_IT.csv"
map["i18n/ja.csv"]="i18n/ja_JP.csv"
map["i18n/ko.csv"]="i18n/ko_KR.csv"
map["i18n/ms.csv"]="i18n/ms_MY.csv"
map["i18n/nb.csv"]="i18n/nb_NO.csv"
map["i18n/nl.csv"]="i18n/nl_BE.csv"
map["i18n/pl.csv"]="i18n/pl_PL.csv"
map["i18n/pt.csv"]="i18n/pt_BR.csv"
map["i18n/pt_PT.csv"]="i18n/pt_PT.csv"
map["i18n/ro.csv"]="i18n/ro_RO.csv"
map["i18n/ru.csv"]="i18n/ru_RU.csv"
map["i18n/sk.csv"]="i18n/sk_SK.csv"
map["i18n/sv.csv"]="i18n/sv_SE.csv"
map["i18n/te.csv"]="i18n/te_TE.csv"
map["i18n/th.csv"]="i18n/th_TH.csv"
map["i18n/tl.csv"]="i18n/tl_TL.csv"
map["i18n/tr.csv"]="i18n/tr_TR.csv"
map["i18n/uk.csv"]="i18n/uk_UA.csv"
map["i18n/vi.csv"]="i18n/vi_VN.csv"
map["i18n/zh.csv"]="i18n/zh_Hans_CN.csv"
map["i18n/zh_TW.csv"]="i18n/zh_TW.csv"

for old in "${!map[@]}"; do
  new=${map[$old]}
  # Check if the old file exists and if it's different from the new file
  if [ "$old" != "$new" ] && [ -f "$old" ]; then
    echo "Renaming $old to $new"
    mv "$old" "$new"
  fi
done
