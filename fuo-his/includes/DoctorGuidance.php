<?php
class DoctorGuidance {
    private static $symptomMap = [
        'fever'       =>['conditions'=>['Malaria','Typhoid Fever','Viral Infection','UTI','Dengue'],'tests'=>['Full Blood Count (FBC)','Malaria Parasite Test','Widal Test','Urinalysis'],'specialty'=>'General Medicine'],
        'malaria'     =>['conditions'=>['Plasmodium Falciparum Malaria','Plasmodium Vivax Malaria'],'tests'=>['Malaria Parasite Test','Full Blood Count (FBC)','Blood Glucose'],'specialty'=>'General Medicine'],
        'chills'      =>['conditions'=>['Malaria','Typhoid Fever','Sepsis','Pneumonia'],'tests'=>['Malaria Parasite Test','Full Blood Count (FBC)','Blood Culture'],'specialty'=>'General Medicine'],
        'headache'    =>['conditions'=>['Malaria','Hypertension','Migraine','Typhoid Fever','Meningitis'],'tests'=>['Blood Pressure Check','Malaria Parasite Test','Full Blood Count (FBC)','Blood Glucose'],'specialty'=>'General Medicine'],
        'migraine'    =>['conditions'=>['Migraine','Cluster Headache','Hypertension'],'tests'=>['Blood Pressure Check','CT Scan (if severe)','Full Blood Count (FBC)'],'specialty'=>'Neurology'],
        'stomach'     =>['conditions'=>['Gastroenteritis','Peptic Ulcer','Appendicitis','Typhoid Fever','Food Poisoning'],'tests'=>['H. Pylori Test','Abdominal Ultrasound','Full Blood Count (FBC)','Stool Culture'],'specialty'=>'General Medicine'],
        'abdominal'   =>['conditions'=>['Appendicitis','Gastroenteritis','Peptic Ulcer','IBS'],'tests'=>['Abdominal Ultrasound','Full Blood Count (FBC)','Liver Function Test','Urinalysis'],'specialty'=>'General Medicine'],
        'vomiting'    =>['conditions'=>['Gastroenteritis','Food Poisoning','Malaria','Typhoid Fever'],'tests'=>['Full Blood Count (FBC)','Malaria Parasite Test','Electrolytes','Stool Culture'],'specialty'=>'General Medicine'],
        'diarrhoea'   =>['conditions'=>['Gastroenteritis','Cholera','Food Poisoning','Typhoid Fever'],'tests'=>['Stool Microscopy & Culture','Full Blood Count (FBC)','Electrolytes'],'specialty'=>'General Medicine'],
        'diarrhea'    =>['conditions'=>['Gastroenteritis','Cholera','Food Poisoning','Typhoid Fever'],'tests'=>['Stool Microscopy & Culture','Full Blood Count (FBC)','Electrolytes'],'specialty'=>'General Medicine'],
        'chest'       =>['conditions'=>['Pneumonia','Myocardial Infarction','Angina','Pulmonary Embolism'],'tests'=>['ECG','Chest X-Ray','Troponin Test','Full Blood Count (FBC)','D-Dimer'],'specialty'=>'Cardiology'],
        'heart'       =>['conditions'=>['Heart Failure','Arrhythmia','Angina','Myocardial Infarction'],'tests'=>['ECG','Echocardiogram','Troponin Test','BNP Level','Lipid Profile'],'specialty'=>'Cardiology'],
        'palpitation' =>['conditions'=>['Arrhythmia','Anxiety','Hyperthyroidism','Anaemia'],'tests'=>['ECG','Thyroid Function Test','Full Blood Count (FBC)'],'specialty'=>'Cardiology'],
        'cough'       =>['conditions'=>['Upper Respiratory Infection','Pneumonia','Tuberculosis','Asthma'],'tests'=>['Chest X-Ray','Sputum Culture','Full Blood Count (FBC)','TB Gene Xpert'],'specialty'=>'General Medicine'],
        'breathing'   =>['conditions'=>['Asthma','Pneumonia','Heart Failure','COPD'],'tests'=>['Chest X-Ray','Peak Flow Test','Oxygen Saturation','Full Blood Count','ECG'],'specialty'=>'General Medicine'],
        'shortness'   =>['conditions'=>['Asthma','Pneumonia','Heart Failure','Anaemia'],'tests'=>['Chest X-Ray','ECG','Full Blood Count (FBC)','Oxygen Saturation'],'specialty'=>'General Medicine'],
        'child'       =>['conditions'=>['Malaria','Measles','Febrile Convulsion','Pneumonia'],'tests'=>['Malaria Parasite Test','Full Blood Count (FBC)','Blood Glucose','Chest X-Ray'],'specialty'=>'Paediatrics'],
        'infant'      =>['conditions'=>['Neonatal Jaundice','Neonatal Sepsis','Respiratory Distress'],'tests'=>['Bilirubin Level','Full Blood Count','Blood Culture','Blood Glucose'],'specialty'=>'Paediatrics'],
        'urine'       =>['conditions'=>['Urinary Tract Infection','Kidney Stones','Cystitis'],'tests'=>['Urinalysis','Urine Culture & Sensitivity','Kidney Function Test','Abdominal Ultrasound'],'specialty'=>'General Medicine'],
        'urination'   =>['conditions'=>['Urinary Tract Infection','Diabetes Mellitus','Prostate Enlargement'],'tests'=>['Urinalysis','Blood Glucose','PSA Test (male)','Urine Culture'],'specialty'=>'General Medicine'],
        'rash'        =>['conditions'=>['Allergic Reaction','Chickenpox','Measles','Eczema','Drug Reaction'],'tests'=>['Full Blood Count (FBC)','Skin Scraping','IgE Level'],'specialty'=>'General Medicine'],
        'itching'     =>['conditions'=>['Allergic Dermatitis','Scabies','Liver Disease','Eczema'],'tests'=>['Liver Function Test','Kidney Function Test','Full Blood Count','IgE Level'],'specialty'=>'General Medicine'],
        'joint'       =>['conditions'=>['Arthritis','Gout','Rheumatoid Arthritis','Sickle Cell Crisis'],'tests'=>['Uric Acid Level','Rheumatoid Factor','Full Blood Count','ESR/CRP'],'specialty'=>'Orthopaedics'],
        'bone'        =>['conditions'=>['Fracture','Osteomyelitis','Osteoporosis'],'tests'=>['X-Ray','Full Blood Count','Bone Density Scan','Calcium Level'],'specialty'=>'Orthopaedics'],
        'back'        =>['conditions'=>['Musculoskeletal Pain','Herniated Disc','Kidney Infection'],'tests'=>['X-Ray (Spine)','MRI Spine','Urinalysis','Full Blood Count'],'specialty'=>'Orthopaedics'],
        'menstrual'   =>['conditions'=>['Dysmenorrhoea','Endometriosis','PCOS','Uterine Fibroids'],'tests'=>['Pelvic Ultrasound','Full Blood Count','Hormone Profile (FSH,LH)'],'specialty'=>'Gynaecology'],
        'pregnancy'   =>['conditions'=>['Normal Pregnancy','Ectopic Pregnancy','Pre-eclampsia','Gestational Diabetes'],'tests'=>['Urine Pregnancy Test','Obstetric Ultrasound','Blood Pressure','Blood Glucose'],'specialty'=>'Gynaecology'],
        'vaginal'     =>['conditions'=>['Vaginal Candidiasis','Bacterial Vaginosis','STI'],'tests'=>['High Vaginal Swab','STI Screen','Pelvic Ultrasound'],'specialty'=>'Gynaecology'],
        'seizure'     =>['conditions'=>['Epilepsy','Febrile Convulsion','Meningitis','Hypoglycaemia'],'tests'=>['Blood Glucose','EEG','CT Scan Head','Electrolytes'],'specialty'=>'Neurology'],
        'dizziness'   =>['conditions'=>['Vertigo','Hypertension','Anaemia','Hypoglycaemia'],'tests'=>['Blood Pressure','Blood Glucose','Full Blood Count','Audiometry'],'specialty'=>'Neurology'],
        'confusion'   =>['conditions'=>['Cerebral Malaria','Meningitis','Hypoglycaemia','Stroke'],'tests'=>['Blood Glucose','Malaria Test','CT Scan Head','Full Blood Count','Lumbar Puncture'],'specialty'=>'Neurology'],
        'stroke'      =>['conditions'=>['Ischaemic Stroke','Haemorrhagic Stroke','TIA'],'tests'=>['CT Scan Head','MRI Brain','ECG','Blood Pressure','Lipid Profile'],'specialty'=>'Neurology'],
        'sugar'       =>['conditions'=>['Diabetes Mellitus Type 2','Hypoglycaemia'],'tests'=>['Fasting Blood Glucose','HbA1c','Urinalysis','Lipid Profile'],'specialty'=>'General Medicine'],
        'diabetes'    =>['conditions'=>['Diabetes Mellitus Type 2','Diabetic Ketoacidosis'],'tests'=>['Fasting Blood Glucose','HbA1c','Kidney Function Test','Urinalysis'],'specialty'=>'General Medicine'],
        'pain'        =>['conditions'=>['Musculoskeletal Pain','Inflammatory Condition','Infection'],'tests'=>['Full Blood Count (FBC)','ESR/CRP (inflammation markers)','Urinalysis'],'specialty'=>'General Medicine'],
        'weakness'    =>['conditions'=>['Anaemia','Malaria','Typhoid Fever','Malnutrition'],'tests'=>['Full Blood Count (FBC)','Malaria Test','Blood Glucose','Electrolytes'],'specialty'=>'General Medicine'],
        'fatigue'     =>['conditions'=>['Anaemia','Hypothyroidism','Diabetes','Malaria'],'tests'=>['Full Blood Count (FBC)','Thyroid Function Test','Blood Glucose','Malaria Test'],'specialty'=>'General Medicine'],
        'swelling'    =>['conditions'=>['Oedema','Heart Failure','Kidney Disease','Liver Disease'],'tests'=>['Kidney Function Test','Liver Function Test','Full Blood Count','Chest X-Ray','ECG'],'specialty'=>'General Medicine'],
        'jaundice'    =>['conditions'=>['Hepatitis A/B/C','Malaria','Sickle Cell Crisis','Liver Cirrhosis'],'tests'=>['Liver Function Test','Hepatitis Screen','Malaria Test','Abdominal Ultrasound'],'specialty'=>'General Medicine'],
        'yellow'      =>['conditions'=>['Jaundice','Hepatitis','Malaria','Sickle Cell Disease'],'tests'=>['Liver Function Test','Hepatitis B & C Screen','Malaria Test','Full Blood Count'],'specialty'=>'General Medicine'],
        'body pain'   =>['conditions'=>['Malaria','Influenza','Typhoid Fever','Fibromyalgia'],'tests'=>['Malaria Parasite Test','Full Blood Count (FBC)','ESR/CRP'],'specialty'=>'General Medicine'],
        'body ache'   =>['conditions'=>['Malaria','Influenza','Typhoid Fever','Viral Infection'],'tests'=>['Malaria Parasite Test','Full Blood Count (FBC)','Widal Test'],'specialty'=>'General Medicine'],
        'hypertension'=>['conditions'=>['Essential Hypertension','Secondary Hypertension'],'tests'=>['Blood Pressure Monitoring','Kidney Function Test','ECG','Urinalysis','Lipid Profile'],'specialty'=>'Cardiology'],
    ];

    public static function analyse($symptomText) {
        $text    = strtolower($symptomText);
        $matched = $allConds = $allTests = $keywords = [];
        $specialty = 'General Medicine';
        foreach (self::$symptomMap as $keyword => $data) {
            if (strpos($text, $keyword) !== false) {
                $matched[]  = $keyword;
                $keywords[] = $keyword;
                foreach ($data['conditions'] as $c) { if (!in_array($c,$allConds)) $allConds[]=$c; }
                foreach ($data['tests']      as $t) { if (!in_array($t,$allTests)) $allTests[]=$t; }
                if ($data['specialty'] !== 'General Medicine') $specialty = $data['specialty'];
            }
        }
        return [
            'matched_keywords'    => $keywords,
            'possible_conditions' => $allConds,
            'recommended_tests'   => $allTests,
            'suggested_specialty' => $specialty,
            'has_guidance'        => !empty($matched),
        ];
    }
}
