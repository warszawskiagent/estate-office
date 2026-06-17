import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { BottomTabNavigationProp } from '@react-navigation/bottom-tabs';
import { CompositeNavigationProp, RouteProp } from '@react-navigation/native';

export type RootStackParamList = {
  Login: undefined;
  Main: undefined;
};

export type MainTabParamList = {
  Dashboard: undefined;
  PropertiesStack: undefined;
  ClientsStack: undefined;
  AgreementsStack: undefined;
  SearchesStack: undefined;
};

export type PropertiesStackParamList = {
  PropertiesList: undefined;
  PropertyDetail: { id: number };
  PropertyForm: { id?: number };
};

export type ClientsStackParamList = {
  ClientsList: undefined;
  ClientDetail: { id: number };
  ClientForm: { id?: number };
};

export type AgreementsStackParamList = {
  AgreementsList: undefined;
  AgreementDetail: { id: number };
  AgreementForm: { id?: number };
};

export type SearchesStackParamList = {
  SearchesList: undefined;
  SearchDetail: { id: number };
  SearchForm: { id?: number };
};

export type RootNavigationProp = NativeStackNavigationProp<RootStackParamList>;

export type PropertiesNavProp = CompositeNavigationProp<
  NativeStackNavigationProp<PropertiesStackParamList>,
  BottomTabNavigationProp<MainTabParamList>
>;

export type ClientsNavProp = CompositeNavigationProp<
  NativeStackNavigationProp<ClientsStackParamList>,
  BottomTabNavigationProp<MainTabParamList>
>;

export type AgreementsNavProp = CompositeNavigationProp<
  NativeStackNavigationProp<AgreementsStackParamList>,
  BottomTabNavigationProp<MainTabParamList>
>;

export type SearchesNavProp = CompositeNavigationProp<
  NativeStackNavigationProp<SearchesStackParamList>,
  BottomTabNavigationProp<MainTabParamList>
>;

export type PropertyDetailRouteProp = RouteProp<PropertiesStackParamList, 'PropertyDetail'>;
export type PropertyFormRouteProp = RouteProp<PropertiesStackParamList, 'PropertyForm'>;
export type ClientDetailRouteProp = RouteProp<ClientsStackParamList, 'ClientDetail'>;
export type ClientFormRouteProp = RouteProp<ClientsStackParamList, 'ClientForm'>;
export type AgreementDetailRouteProp = RouteProp<AgreementsStackParamList, 'AgreementDetail'>;
export type AgreementFormRouteProp = RouteProp<AgreementsStackParamList, 'AgreementForm'>;
export type SearchDetailRouteProp = RouteProp<SearchesStackParamList, 'SearchDetail'>;
export type SearchFormRouteProp = RouteProp<SearchesStackParamList, 'SearchForm'>;
